<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\MouvementStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProduitController extends Controller
{
    // Lister tous les produits (Sécurisé pour intercepter l'erreur 500)
    public function index()
    {
        try {
            $produits = Produit::all();
            return response()->json($produits);
        } catch (\Exception $e) {
            Log::error('Erreur index produit : ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des produits : ' . $e->getMessage()
            ], 500);
        }
    }

    // Créer un produit
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nom'                    => 'required|string',
                'reference'              => 'required|string|unique:produits,reference',
                'prix_unitaire'          => 'required|numeric',
                'prix_gros'              => 'nullable|numeric',
                'quantite_stock'         => 'required|integer',
                'unite'                  => 'nullable|string',
                'commission_fixe'        => 'nullable|numeric|min:0',
                'commission_pourcentage' => 'nullable|numeric|min:0|max:100',
            ]);

            // Vérification de sécurité pour l'utilisateur connecté
            if (!$request->user()) {
                return response()->json([
                    'message' => 'Une authentification est requise pour ajouter un produit.'
                ], 401);
            }

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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erreur stockage produit : ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur serveur : ' . $e->getMessage()
            ], 500);
        }
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
            'nom'                    => 'sometimes|string',
            'prix_unitaire'          => 'sometimes|numeric',
            'prix_gros'              => 'nullable|numeric',
            'quantite_stock'         => 'sometimes|integer',
            'unite'                  => 'nullable|string',
            'commission_fixe'        => 'nullable|numeric|min:0',
            'commission_pourcentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $produit->update($request->all());
        return response()->json($produit);
    }

    // Supprimer un produit
    public function destroy($id)
    {
        $produit = Produit::findOrFail($id);

        // Vérifier si le produit a des ventes liées
        $nbVentes = \Illuminate\Support\Facades\DB::table('vente_items')
            ->where('produit_id', $id)->count();

        if ($nbVentes > 0) {
            // Produit lié à des ventes — désactiver au lieu de supprimer
            $produit->update([
                'actif'           => false,
                'quantite_stock'  => 0,
                'nom'             => '[Archivé] ' . $produit->nom,
            ]);
            return response()->json([
                'message' => 'Produit archivé (lié à ' . $nbVentes . ' vente(s) — données préservées)'
            ]);
        }

        // Aucune vente liée — suppression définitive
        $produit->delete();
        return response()->json([
            'message' => 'Produit supprimé définitivement'
        ]);
    }
}
