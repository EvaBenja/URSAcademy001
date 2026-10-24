<?php

namespace App\Http\Controllers;

use App\Models\BilanLivreur;
use App\Models\BilanLigne;
use Illuminate\Http\Request;

class BilanLivreurController extends Controller
{
    // Gestionnaire crée le bilan du jour pour un livreur
    public function store(Request $request)
    {
        $roleNom = $request->user()?->role?->nom;
        if (!in_array($roleNom, ['gestionnaire', 'super_admin'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $request->validate([
            'livreur_id' => 'required|exists:users,id',
            'date'       => 'required|date',
            'lignes'     => 'required|array|min:1',
            'lignes.*.produit_id'  => 'required|exists:produits,id',
            'lignes.*.qte_remise'  => 'required|integer|min:1',
        ]);

        // Eviter doublon bilan même jour même livreur
        $existant = BilanLivreur::where('livreur_id', $request->livreur_id)
            ->where('date', $request->date)
            ->first();

        if ($existant) {
            return response()->json(['message' => 'Un bilan existe déjà pour ce livreur ce jour'], 422);
        }

        $bilan = BilanLivreur::create([
            'livreur_id' => $request->livreur_id,
            'date'       => $request->date,
            'statut'     => 'ouvert',
        ]);

        foreach ($request->lignes as $ligne) {
            BilanLigne::create([
                'bilan_id'   => $bilan->id,
                'produit_id' => $ligne['produit_id'],
                'qte_remise' => $ligne['qte_remise'],
                'qte_livree' => 0,
                'qte_retour' => 0,
            ]);
        }

        return response()->json($bilan->load('lignes.produit'), 201);
    }

    // Livreur consulte son bilan du jour
    public function monBilan(Request $request)
    {
        $bilan = BilanLivreur::with('lignes.produit')
            ->where('livreur_id', $request->user()->id)
            ->whereDate('date', today())
            ->first();

        if (!$bilan) {
            return response()->json(['message' => 'Aucun bilan pour aujourd\'hui'], 404);
        }

        return response()->json($bilan);
    }

    // Livreur clôture son bilan
    public function cloturer(Request $request, $id)
    {
        $bilan = BilanLivreur::with('lignes')->findOrFail($id);

        if ($bilan->livreur_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if ($bilan->statut === 'cloture') {
            return response()->json(['message' => 'Bilan déjà clôturé'], 422);
        }

        $request->validate([
            'lignes'              => 'required|array',
            'lignes.*.id'         => 'required|exists:bilan_lignes,id',
            'lignes.*.qte_livree' => 'required|integer|min:0',
            'lignes.*.qte_retour' => 'required|integer|min:0',
            'notes'               => 'nullable|string',
        ]);

        foreach ($request->lignes as $ligneData) {
            BilanLigne::where('id', $ligneData['id'])
                ->where('bilan_id', $bilan->id)
                ->update([
                    'qte_livree' => $ligneData['qte_livree'],
                    'qte_retour' => $ligneData['qte_retour'],
                ]);
        }

        $bilan->update([
            'statut'    => 'cloture',
            'valide_at' => now(),
            'notes'     => $request->notes,
        ]);

        return response()->json($bilan->load('lignes.produit'));
    }

    // Gestionnaire liste tous les bilans
    public function index(Request $request)
    {
        $roleNom = $request->user()?->role?->nom;
        if (!in_array($roleNom, ['gestionnaire', 'super_admin', 'compta'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $date = $request->query('date', today()->format('Y-m-d'));

        $bilans = BilanLivreur::with(['livreur', 'lignes.produit'])
            ->whereDate('date', $date)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bilans);
    }
}