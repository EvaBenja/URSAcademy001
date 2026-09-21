<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Produit;
use App\Models\Vente;
use App\Models\VenteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BoutiqueVendeurController extends Controller
{
    // ── Générer ou récupérer le lien boutique du vendeur ──
    public function monLien(Request $request)
    {
        $user = $request->user();

        if (!$user->code_boutique) {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $user->name), 0, 6))
                  . '-' . strtoupper(Str::random(4));
            $user->update(['code_boutique' => $code]);
        }

        $lien = config('app.url') . '/boutique/' . $user->code_boutique;

        return response()->json([
            'code_boutique'       => $user->code_boutique,
            'lien'                => $lien,
            'nom_boutique'        => $user->nom_boutique ?? $user->name,
            'description_boutique'=> $user->description_boutique,
        ]);
    }

    // ── Modifier les infos de sa boutique ──
    public function updateBoutique(Request $request)
    {
        $request->validate([
            'nom_boutique'         => 'nullable|string|max:100',
            'description_boutique' => 'nullable|string',
        ]);

        $user = $request->user();
        $user->update([
            'nom_boutique'         => $request->nom_boutique,
            'description_boutique' => $request->description_boutique,
        ]);

        return response()->json([
            'message'      => 'Boutique mise à jour',
            'nom_boutique' => $user->nom_boutique,
        ]);
    }

    // ── Page publique boutique vendeur (accessible sans authentification) ──
    public function boutiquePub($code)
    {
        $vendeur = User::where('code_boutique', $code)
            ->whereHas('role', fn($q) => $q->where('nom', 'vendeur'))
            ->first();

        if (!$vendeur) {
            return response()->json(['message' => 'Boutique introuvable'], 404);
        }

        // Récupérer les produits disponibles de la boutique
        $produits = Produit::where('boutique_id', $vendeur->boutique_id)
            ->where('quantite_stock', '>', 0)
            ->select(['id', 'nom', 'reference', 'prix_unitaire', 'prix_gros', 'unite', 'quantite_stock'])
            ->get();

        return response()->json([
            'vendeur' => [
                'nom'          => $vendeur->nom_boutique ?? $vendeur->name,
                'description'  => $vendeur->description_boutique,
                'code_boutique'=> $vendeur->code_boutique,
            ],
            'produits' => $produits,
        ]);
    }

    // ── Commander depuis la boutique publique (sans authentification) ──
    public function commander(Request $request, $code)
    {
        $request->validate([
            'client_nom'       => 'required|string',
            'client_telephone' => 'required|string',
            'client_adresse'   => 'nullable|string',
            'client_ville'     => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.produit_id' => 'required|exists:produits,id',
            'items.*.quantite'   => 'required|integer|min:1',
        ]);

        $vendeur = User::where('code_boutique', $code)
            ->whereHas('role', fn($q) => $q->where('nom', 'vendeur'))
            ->first();

        if (!$vendeur) {
            return response()->json(['message' => 'Boutique introuvable'], 404);
        }

        $montantTotal = 0;
        $itemsData    = [];

        foreach ($request->items as $item) {
            $produit = Produit::findOrFail($item['produit_id']);

            if ($produit->quantite_stock < $item['quantite']) {
                return response()->json([
                    'message' => 'Stock insuffisant pour : ' . $produit->nom
                ], 422);
            }

            $sousTotal     = $produit->prix_unitaire * $item['quantite'];
            $montantTotal += $sousTotal;

            $itemsData[] = [
                'produit'    => $produit,
                'quantite'   => $item['quantite'],
                'prix'       => $produit->prix_unitaire,
                'sous_total' => $sousTotal,
            ];
        }

        // Créer la vente automatiquement au nom du vendeur
        $vente = Vente::create([
            'caissiere_id'     => $vendeur->id,
            'boutique_id'      => $vendeur->boutique_id,
            'produit_id'       => null,
            'quantite'         => count($itemsData),
            'prix_unitaire'    => $montantTotal,
            'prix_vendeur'     => $montantTotal,
            'remise'           => 0,
            'montant_total'    => $montantTotal,
            'date_vente'       => now()->toDateString(),
            'zone_livraison'   => $request->client_ville,
            'statut'           => 'en_attente',
            'client_nom'       => $request->client_nom,
            'client_telephone' => $request->client_telephone,
            'client_quartier'  => $request->client_adresse,
            'notes'            => 'Commande boutique en ligne vendeur ' . ($vendeur->nom_boutique ?? $vendeur->name),
        ]);

        // Créer les items de vente
        foreach ($itemsData as $item) {
            VenteItem::create([
                'vente_id'     => $vente->id,
                'produit_id'   => $item['produit']->id,
                'quantite'     => $item['quantite'],
                'prix_unitaire'=> $item['prix'],
                'prix_vendeur' => $item['prix'],
                'remise'       => 0,
                'sous_total'   => $item['sous_total'],
            ]);

            // Décrémenter le stock
            $item['produit']->decrement('quantite_stock', $item['quantite']);
        }

        return response()->json([
            'message'    => 'Commande passée avec succès !',
            'vente_id'   => $vente->id,
            'montant'    => $montantTotal,
            'vendeur'    => $vendeur->nom_boutique ?? $vendeur->name,
        ], 201);
    }

    // ── Stats de la boutique du vendeur ──
    public function mesBoutiqueStats(Request $request)
    {
        $user = $request->user();

        $ventes = Vente::where('caissiere_id', $user->id)
            ->whereNotNull('client_nom')
            ->get();

        return response()->json([
            'code_boutique'  => $user->code_boutique,
            'lien'           => config('app.url') . '/boutique/' . $user->code_boutique,
            'nb_commandes'   => $ventes->count(),
            'ca_total'       => $ventes->sum('montant_total'),
            'ca_aujourd_hui' => $ventes->whereDate('date_vente', today())->sum('montant_total'),
        ]);
    }
}