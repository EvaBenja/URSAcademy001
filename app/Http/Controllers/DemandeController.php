<?php
namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\LivraisonProduit;
use App\Models\DossierJournalier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DemandeController extends Controller
{
    public function index(Request $request)
    {
        $user    = $request->user();
        $roleNom = $user->role?->nom ?? '';

        if ($roleNom === 'livreur') {
            $demandes = Livraison::with(['livreur','gestionnaire','produits.produit'])
                ->where('livreur_id', $user->id)
                ->orderByDesc('created_at')
                ->get();
            return response()->json($demandes);
        }

        // Gestionnaire/coordinateur voient les demandes créées par des livreurs
        $demandes = Livraison::with(['livreur','gestionnaire','produits.produit'])
            ->whereHas('livreur', fn($q) => $q->whereHas('role', fn($r) => $r->where('nom','livreur')))
            ->orderByDesc('created_at')
            ->get();

        return response()->json($demandes);
    }

    // Livreur crée une demande avec des produits à livrer
    public function store(Request $request)
    {
        $request->validate([
            'date_livraison'        => 'required|date',
            'notes'                 => 'nullable|string',
            'zone_livraison'        => 'nullable|string',
            'produits'              => 'nullable|array',
            'produits.*.produit_id' => 'required_with:produits|exists:produits,id',
            'produits.*.quantite'   => 'required_with:produits|integer|min:1',
        ]);

        $demande = Livraison::create([
            'livreur_id'     => $request->user()->id,
            'statut'         => 'en_attente',
            'date_livraison' => $request->date_livraison,
            'notes'          => $request->notes,
            'zone_livraison' => $request->zone_livraison,
        ]);

        // Associer les produits si fournis et si table existe
        if ($request->has('produits') && is_array($request->produits) && Schema::hasTable('livraison_produits')) {
            foreach ($request->produits as $item) {
                LivraisonProduit::create([
                    'livraison_id' => $demande->id,
                    'produit_id'   => $item['produit_id'],
                    'quantite'     => $item['quantite'],
                ]);
            }
        }

        return response()->json($demande->load(['livreur','produits.produit']), 201);
    }

    // Gestionnaire valide → crée dossier journalier
    // montant_carburant est optionnel (défaut 0)
    public function valider(Request $request, $id)
    {
        $request->validate([
            'montant_carburant' => 'nullable|numeric|min:0',
        ]);

        $demande = Livraison::findOrFail($id);

        $demande->update([
            'gestionnaire_id' => $request->user()->id,
            'statut'          => 'validee',
        ]);

        DossierJournalier::create([
            'livreur_id'        => $demande->livreur_id,
            'livraison_id'      => $demande->id,
            'montant_carburant' => $request->montant_carburant ?? 0,
            'statut'            => 'ouvert',
            'date'              => $demande->date_livraison,
        ]);

        return response()->json($demande->load(['livreur','gestionnaire']));
    }

    // Gestionnaire refuse — motif obligatoire
    public function refuser(Request $request, $id)
    {
        $request->validate(['motif' => 'required|string']);
        $demande = Livraison::findOrFail($id);
        $demande->update([
            'statut'      => 'rejetee',
            'motif_rejet' => $request->motif,
        ]);
        return response()->json(['message' => 'Demande refusée', 'demande' => $demande]);
    }
}
