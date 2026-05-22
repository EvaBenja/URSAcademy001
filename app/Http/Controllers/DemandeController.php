<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\DossierJournalier;
use Illuminate\Http\Request;

class DemandeController extends Controller
{
    // Lister toutes les demandes
    public function index()
    {
        $demandes = Livraison::with(['livreur', 'gestionnaire'])
            ->orderByDesc('created_at')
            ->get();
        return response()->json($demandes);
    }

    // Créer une demande
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

    // Valider une demande
    public function valider(Request $request, $id)
    {
        $demande = Livraison::findOrFail($id);

        $request->validate([
            'montant_carburant' => 'required|numeric|min:0',
        ]);

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

    // Refuser une demande
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

        return response()->json([
            'message' => 'Demande refusée',
            'demande' => $demande
        ]);
    }
}