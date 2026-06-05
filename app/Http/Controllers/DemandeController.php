<?php
namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\DossierJournalier;
use Illuminate\Http\Request;

class DemandeController extends Controller
{
    // Lister les demandes livreurs
    // Une demande livreur = livreur_id non null ET (gestionnaire_id null OU soumis par livreur)
    public function index(Request $request)
    {
        $user = $request->user();
        $roleNom = $user->role?->nom ?? '';

        // Si livreur → seulement ses propres demandes
        if ($roleNom === 'livreur') {
            $demandes = Livraison::with(['livreur', 'gestionnaire'])
                ->where('livreur_id', $user->id)
                ->orderByDesc('created_at')
                ->get();
            return response()->json($demandes);
        }

        // Si gestionnaire/coordinateur/admin → toutes les demandes soumises par livreurs
        // On identifie une demande livreur par: livreur_id = l'utilisateur qui l'a créée
        // et gestionnaire_id NULL (pas encore traitée) OU statut != terminee
        $demandes = Livraison::with(['livreur', 'gestionnaire'])
            ->whereHas('livreur', function ($q) {
                $q->whereHas('role', function ($r) {
                    $r->where('nom', 'livreur');
                });
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($demandes);
    }

    // Livreur crée une demande
    public function store(Request $request)
    {
        $request->validate([
            'date_livraison' => 'required|date',
            'notes'          => 'nullable|string',
            'zone_livraison' => 'nullable|string',
        ]);

        $demande = Livraison::create([
            'livreur_id'     => $request->user()->id,
            'statut'         => 'en_attente',
            'date_livraison' => $request->date_livraison,
            'notes'          => $request->notes,
            'zone_livraison' => $request->zone_livraison,
        ]);

        return response()->json($demande->load('livreur'), 201);
    }

    // Gestionnaire valide → crée dossier journalier
    public function valider(Request $request, $id)
    {
        $request->validate([
            'montant_carburant' => 'required|numeric|min:0',
        ]);

        $demande = Livraison::findOrFail($id);

        $demande->update([
            'gestionnaire_id' => $request->user()->id,
            'statut'          => 'validee',
        ]);

        DossierJournalier::create([
            'livreur_id'        => $demande->livreur_id,
            'livraison_id'      => $demande->id,
            'montant_carburant' => $request->montant_carburant,
            'statut'            => 'ouvert',
            'date'              => $demande->date_livraison,
        ]);

        return response()->json($demande->load(['livreur', 'gestionnaire']));
    }

    // Gestionnaire refuse
    public function refuser(Request $request, $id)
    {
        $request->validate([
            'motif' => 'required|string',
        ]);

        $demande = Livraison::findOrFail($id);
        $demande->update([
            'statut'      => 'rejetee',
            'motif_rejet' => $request->motif,
        ]);

        return response()->json(['message' => 'Demande refusée', 'demande' => $demande]);
    }
}
