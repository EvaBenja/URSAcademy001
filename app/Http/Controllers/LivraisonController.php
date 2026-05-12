<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\DossierJournalier;
use Illuminate\Http\Request;

class LivraisonController extends Controller
{
    // Lister toutes les livraisons
    public function index()
    {
        $livraisons = Livraison::with(['livreur', 'gestionnaire'])->get();
        return response()->json($livraisons);
    }

    // Livreur soumet une demande
    public function store(Request $request)
    {
        $request->validate([
            'date_livraison' => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        $livraison = Livraison::create([
            'livreur_id'     => $request->user()->id,
            'statut'         => 'en_attente',
            'date_livraison' => $request->date_livraison,
            'notes'          => $request->notes,
        ]);

        return response()->json($livraison->load('livreur'), 201);
    }

    // Voir une livraison
    public function show($id)
    {
        $livraison = Livraison::with(['livreur', 'gestionnaire', 'dossier'])->findOrFail($id);
        return response()->json($livraison);
    }

    // Gestionnaire valide la livraison
    public function valider(Request $request, $id)
    {
        $livraison = Livraison::findOrFail($id);

        if ($livraison->statut !== 'en_attente') {
            return response()->json([
                'message' => 'Cette livraison ne peut plus être modifiée'
            ], 422);
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

        // Créer le dossier journalier
        DossierJournalier::create([
            'livreur_id'        => $livraison->livreur_id,
            'livraison_id'      => $livraison->id,
            'montant_carburant' => $request->montant_carburant,
            'statut'            => 'ouvert',
            'date'              => $livraison->date_livraison,
        ]);

        return response()->json($livraison->load(['livreur', 'gestionnaire', 'dossier']));
    }

    // Clôturer le dossier journalier
    public function cloturer($id)
    {
        $livraison = Livraison::findOrFail($id);
        $dossier   = $livraison->dossier;

        if (!$dossier) {
            return response()->json([
                'message' => 'Aucun dossier journalier trouvé'
            ], 404);
        }

        $dossier->update(['statut' => 'cloture']);
        $livraison->update(['statut' => 'terminee']);

        return response()->json([
            'message' => 'Dossier clôturé avec succès',
            'dossier' => $dossier
        ]);
    }
}