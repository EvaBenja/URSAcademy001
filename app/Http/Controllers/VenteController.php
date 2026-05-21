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
            'produit_id'     => 'required|exists:produits,id',
            'quantite'       => 'required|integer|min:1',
            'date_vente'     => 'required|date',
            'prix_vendeur'   => 'nullable|numeric|min:0',
            'remise'         => 'nullable|numeric|min:0',
            'zone_livraison' => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $produit = Produit::findOrFail($request->produit_id);

        // Vérifier stock suffisant
        if ($produit->quantite_stock < $request->quantite) {
            return response()->json([
                'message' => 'Stock insuffisant. Disponible : ' . $produit->quantite_stock
            ], 422);
        }

        // Prix : celui du vendeur ou prix normal
        $prix_unitaire = $request->prix_vendeur ?? $produit->prix_unitaire;
        $remise        = $request->remise ?? 0;
        $montant_total = ($prix_unitaire * $request->quantite) - $remise;

        $vente = Vente::create([
            'produit_id'     => $request->produit_id,
            'caissiere_id'   => $request->user()->id,
            'quantite'       => $request->quantite,
            'prix_unitaire'  => $produit->prix_unitaire,
            'prix_vendeur'   => $prix_unitaire,
            'remise'         => $remise,
            'montant_total'  => $montant_total,
            'date_vente'     => $request->date_vente,
            'zone_livraison' => $request->zone_livraison,
            'statut'         => 'en_attente',
            'notes'          => $request->notes,
        ]);

        // Mettre à jour le stock
        $produit->decrement('quantite_stock', $request->quantite);

        // Vérifier rupture de stock
        if ($produit->quantite_stock <= 0) {
            // TODO: envoyer notification rupture de stock
        }

        return response()->json($vente->load(['produit', 'caissiere']), 201);
    }

    // Valider une vente (gestionnaire)
    public function valider($id)
    {
        $vente = Vente::findOrFail($id);
        $vente->update(['statut' => 'validee']);
        return response()->json($vente->load(['produit', 'caissiere']));
    }

    // Annuler une vente
    public function annuler($id)
    {
        $vente = Vente::findOrFail($id);

        // Remettre le stock
        $vente->produit->increment('quantite_stock', $vente->quantite);
        $vente->update(['statut' => 'annulee']);

        return response()->json(['message' => 'Vente annulée et stock remis']);
    }

    // Chiffre d'affaires global
    public function chiffreAffaires()
    {
        $total = Vente::where('statut', 'validee')->sum('montant_total');
        return response()->json(['chiffre_affaires' => $total]);
    }

    // Classement des vendeurs du jour
    public function classementVendeurs()
    {
        $classement = Vente::with('caissiere')
            ->whereDate('date_vente', today())
            ->where('statut', 'validee')
            ->selectRaw('caissiere_id, SUM(montant_total) as total, COUNT(*) as nombre_ventes')
            ->groupBy('caissiere_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item, $index) {
                return [
                    'rang'          => $index + 1,
                    'vendeur'       => $item->caissiere->name,
                    'total'         => $item->total,
                    'nombre_ventes' => $item->nombre_ventes,
                ];
            });

        return response()->json($classement);
    }

    // Chiffre d'affaires par vendeur
    public function chiffreAffairesParCaissiere()
    {
        $stats = Vente::with('caissiere')
            ->where('statut', 'validee')
            ->selectRaw('caissiere_id, SUM(montant_total) as total')
            ->groupBy('caissiere_id')
            ->get();
        return response()->json($stats);
    }
}