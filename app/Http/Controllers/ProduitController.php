<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\MouvementStock;
use Illuminate\Http\Request;

class ProduitController extends Controller
{
    // Lister tous les produits
    public function index()
    {
        $produits = Produit::all();
        return response()->json($produits);
    }

    // Créer un produit
    public function store(Request $request)
    {
        $request->validate([
            'nom'           => 'required|string',
            'reference'     => 'required|string|unique:produits',
            'prix_unitaire' => 'required|numeric',
            'quantite_stock'=> 'required|integer',
            'unite'         => 'nullable|string',
        ]);

        $produit = Produit::create($request->all());

        // Enregistrer le mouvement de stock
        MouvementStock::create([
            'produit_id' => $produit->id,
            'user_id'    => $request->user()->id,
            'type'       => 'entree',
            'quantite'   => $produit->quantite_stock,
            'motif'      => 'Stock initial',
        ]);

        return response()->json($produit, 201);
    }

    // Voir un produit
    public function show($id)
    {
        $produit = Produit::findOrFail($id);
        return response()->json($produit);
    }

    // Modifier un produit
    public function update(Request $request, $id)
    {
        $produit = Produit::findOrFail($id);

        $request->validate([
            'nom'           => 'sometimes|string',
            'prix_unitaire' => 'sometimes|numeric',
            'quantite_stock'=> 'sometimes|integer',
            'unite'         => 'nullable|string',
        ]);

        $produit->update($request->all());
        return response()->json($produit);
    }

    // Supprimer un produit
    public function destroy($id)
    {
        $produit = Produit::findOrFail($id);
        $produit->delete();
        return response()->json([
            'message' => 'Produit supprimé avec succès'
        ]);
    }
}