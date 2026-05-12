<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\Produit;
use Illuminate\Http\Request;

class VenteController extends Controller
{
    // Lister toutes les ventes
    public function index()
    {
        $ventes = Vente::with(['produit', 'caissiere'])->get();
        return response()->json($ventes);
    }

    // Enregistrer une vente
    public function store(Request $request)
    {
        $request->validate([
            'produit_id' => 'required|exists:produits,id',
            'quantite'   => 'required|integer|min:1',
            'date_vente' => 'required|date',
        ]);

        $produit = Produit::findOrFail($request->produit_id);

        // Vérifier stock suffisant
        if ($produit->quantite_stock < $request->quantite) {
            return response()->json([
                'message' => 'Stock insuffisant. Disponible : ' . $produit->quantite_stock
            ], 422);
        }

        $montant_total = $produit->prix_unitaire * $request->quantite;

        $vente = Vente::create([
            'produit_id'    => $request->produit_id,
            'caissiere_id'  => $request->user()->id,
            'quantite'      => $request->quantite,
            'prix_unitaire' => $produit->prix_unitaire,
            'montant_total' => $montant_total,
            'date_vente'    => $request->date_vente,
        ]);

        // Mettre à jour le stock
        $produit->decrement('quantite_stock', $request->quantite);

        return response()->json($vente->load(['produit', 'caissiere']), 201);
    }

    // Chiffre d'affaires global
    public function chiffreAffaires()
    {
        $total = Vente::sum('montant_total');
        return response()->json(['chiffre_affaires' => $total]);
    }

    // Chiffre d'affaires par caissière
    public function chiffreAffairesParCaissiere()
    {
        $stats = Vente::with('caissiere')
            ->selectRaw('caissiere_id, SUM(montant_total) as total')
            ->groupBy('caissiere_id')
            ->get();
        return response()->json($stats);
    }
}