<?php
namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\LivraisonProduit;
use App\Models\DossierJournalier;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\Request;

class LivraisonController extends Controller
{
    public function index()
    {
        $livraisons = Livraison::with(['livreur','gestionnaire','produits.produit'])
            ->orderByDesc('created_at')->get();
        return response()->json($livraisons);
    }

    // Livreur soumet une demande avec plusieurs produits
    public function store(Request $request)
    {
        $request->validate([
            'date_livraison'  => 'required|date',
            'notes'           => 'nullable|string',
            'zone_livraison'  => 'nullable|string',
            'produits'        => 'nullable|array',
            'produits.*.produit_id' => 'required_with:produits|exists:produits,id',
            'produits.*.quantite'   => 'required_with:produits|integer|min:1',
        ]);

        $livraison = Livraison::create([
            'livreur_id'     => $request->user()->id,
            'statut'         => 'en_attente',
            'date_livraison' => $request->date_livraison,
            'notes'          => $request->notes,
            'zone_livraison' => $request->zone_livraison ?? null,
        ]);

        // Associer les produits si fournis
        if ($request->has('produits') && is_array($request->produits)) {
            foreach ($request->produits as $item) {
                LivraisonProduit::create([
                    'livraison_id' => $livraison->id,
                    'produit_id'   => $item['produit_id'],
                    'quantite'     => $item['quantite'],
                ]);
            }
        }

        return response()->json($livraison->load(['livreur','produits.produit']), 201);
    }

    public function show($id)
    {
        $livraison = Livraison::with(['livreur','gestionnaire','dossier','produits.produit'])->findOrFail($id);
        return response()->json($livraison);
    }

    public function valider(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);
        if ($livraison->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette livraison ne peut plus être modifiée'], 422);
        }
        $request->validate([
            'montant_carburant' => 'required|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);
        $livraison->update([
            'gestionnaire_id' => $request->user()->id,
            'statut'          => 'validee',
            'notes'           => $request->notes ?? $livraison->notes,
        ]);
        DossierJournalier::create([
            'livreur_id'        => $livraison->livreur_id,
            'livraison_id'      => $livraison->id,
            'montant_carburant' => $request->montant_carburant,
            'statut'            => 'ouvert',
            'date'              => $livraison->date_livraison,
        ]);
        return response()->json($livraison->load(['livreur','gestionnaire','dossier','produits.produit']));
    }

    public function accepter(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);
        $livraison->update(['statut' => 'en_cours', 'livreur_id' => $request->user()->id]);
        return response()->json(['message' => 'Livraison acceptée', 'livraison' => $livraison->load('livreur')]);
    }

    public function rejeter(Request $request, $id)
    {
        $request->validate(['motif' => 'required|string']);
        $livraison = Livraison::findOrFail($id);
        $livraison->update(['statut' => 'rejetee', 'motif_rejet' => $request->motif]);
        return response()->json(['message' => 'Livraison rejetée — coordonnateur alerté', 'livraison' => $livraison]);
    }

    public function assignerLivreur(Request $request, $id)
    {
        $request->validate(['latitude' => 'required|numeric', 'longitude' => 'required|numeric']);
        $livraison = Livraison::findOrFail($id);
        $lat = $request->latitude;
        $lng = $request->longitude;
        $livreur = User::whereHas('role', fn($q) => $q->where('nom', 'livreur'))
            ->whereNotNull('latitude')
            ->selectRaw("id, name, prenom, nom, email, latitude, longitude,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude)))) AS distance_km",
                [$lat, $lng, $lat])
            ->orderBy('distance_km')->first();
        if (!$livreur) {
            return response()->json(['message' => 'Aucun livreur disponible'], 404);
        }
        $livraison->update(['livreur_id' => $livreur->id]);
        return response()->json([
            'message'   => 'Livreur assigné automatiquement',
            'livreur'   => $livreur->prenom.' '.$livreur->nom,
            'distance'  => round($livreur->distance_km, 2).' km',
            'livraison' => $livraison->load('livreur'),
        ]);
    }

    public function updateStatut(Request $request, $id)
    {
        $request->validate(['statut' => 'required|in:en_attente,validee,en_cours,rejetee,terminee']);
        $livraison = Livraison::findOrFail($id);
        $livraison->update(['statut' => $request->statut]);
        return response()->json($livraison->load(['livreur','gestionnaire']));
    }

    public function cloturer($id)
    {
        $livraison = Livraison::findOrFail($id);
        $dossier   = $livraison->dossier;
        if (!$dossier) {
            return response()->json(['message' => 'Aucun dossier journalier trouvé'], 404);
        }
        $dossier->update(['statut' => 'cloture']);
        $livraison->update(['statut' => 'terminee']);
        return response()->json(['message' => 'Dossier clôturé', 'dossier' => $dossier]);
    }
}
