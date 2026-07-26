<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\User;
use Illuminate\Http\Request;

class BoutiqueController extends Controller
{
    // Lister toutes les boutiques (super_admin uniquement)
    public function index()
    {
        $boutiques = Boutique::withCount(['users', 'produits', 'ventes'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($boutiques);
    }

    // Créer une nouvelle boutique
    public function store(Request $request)
    {
        $request->validate([
            'nom'       => 'required|string|max:100',
            'pays'      => 'required|string|max:100',
            'ville'     => 'nullable|string|max:100',
            'adresse'   => 'nullable|string',
            'telephone' => 'nullable|string',
            'email'     => 'nullable|email',
        ]);

        $boutique = Boutique::create([
            'nom'             => $request->nom,
            'pays'            => $request->pays,
            'ville'           => $request->ville,
            'adresse'         => $request->adresse,
            'telephone'       => $request->telephone,
            'email'           => $request->email,
            'code_invitation' => Boutique::genererCode($request->nom),
            'actif'           => true,
        ]);

        return response()->json($boutique, 201);
    }

    // Voir une boutique
    public function show($id)
    {
        $boutique = Boutique::withCount(['users', 'produits', 'ventes'])
            ->findOrFail($id);
        return response()->json($boutique);
    }

    // Modifier une boutique
    public function update(Request $request, $id)
    {
        $request->validate([
            'nom'       => 'nullable|string|max:100',
            'pays'      => 'nullable|string|max:100',
            'ville'     => 'nullable|string|max:100',
            'adresse'   => 'nullable|string',
            'telephone' => 'nullable|string',
            'email'     => 'nullable|email',
            'actif'     => 'nullable|boolean',
        ]);

        $boutique = Boutique::findOrFail($id);
        $boutique->update($request->only([
            'nom', 'pays', 'ville', 'adresse', 'telephone', 'email', 'actif'
        ]));

        return response()->json($boutique);
    }

    // Supprimer une boutique
    public function destroy($id)
    {
        $boutique = Boutique::findOrFail($id);
        
        // Vérifier qu'il ne reste pas d'utilisateurs actifs
        if ($boutique->users()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer — des utilisateurs sont encore rattachés à cette boutique'
            ], 422);
        }

        $boutique->delete();
        return response()->json(['message' => 'Boutique supprimée']);
    }

    // Rejoindre une boutique via code d'invitation
    public function rejoindre(Request $request)
    {
        $request->validate([
            'code_invitation' => 'required|string',
        ]);

        $boutique = Boutique::where('code_invitation', $request->code_invitation)
            ->where('actif', true)
            ->first();

        if (!$boutique) {
            return response()->json(['message' => 'Code d\'invitation invalide ou boutique inactive'], 404);
        }

        // Rattacher l'utilisateur connecté à cette boutique
        $user = $request->user();
        $user->update(['boutique_id' => $boutique->id]);

        return response()->json([
            'message'  => 'Vous avez rejoint la boutique ' . $boutique->nom,
            'boutique' => $boutique,
        ]);
    }

    // Changer de boutique (pour les utilisateurs multi-boutiques)
    public function switcher(Request $request)
    {
        $request->validate([
            'boutique_id' => 'required|exists:boutiques,id',
        ]);

        $boutique = Boutique::findOrFail($request->boutique_id);

        if (!$boutique->actif) {
            return response()->json(['message' => 'Cette boutique est inactive'], 422);
        }

        $user = $request->user();
        $user->update(['boutique_id' => $boutique->id]);

        return response()->json([
            'message'  => 'Boutique changée : ' . $boutique->nom,
            'boutique' => $boutique,
        ]);
    }

    // Régénérer le code d'invitation
    public function regenererCode($id)
    {
        $boutique = Boutique::findOrFail($id);
        $boutique->update([
            'code_invitation' => Boutique::genererCode($boutique->nom),
        ]);

        return response()->json([
            'message'         => 'Code d\'invitation régénéré',
            'code_invitation' => $boutique->code_invitation,
        ]);
    }

    // Stats d'une boutique
    public function stats($id)
    {
        $boutique = Boutique::findOrFail($id);

        return response()->json([
            'boutique'         => $boutique->nom,
            'nb_utilisateurs'  => $boutique->users()->count(),
            'nb_produits'      => $boutique->produits()->count(),
            'nb_ventes'        => $boutique->ventes()->count(),
            'ca_total'         => $boutique->ventes()->sum('montant_total'),
            'nb_livraisons'    => $boutique->livraisons()->count(),
        ]);
    }

    // ── Catalogue public — accessible sans authentification ──
    public function cataloguePublic($code)
    {
        $boutique = Boutique::where('code_invitation', $code)
            ->where('actif', true)
            ->first();

        if (!$boutique) {
            return response()->json(['message' => 'Boutique introuvable ou inactive'], 404);
        }

        $produits = \App\Models\Produit::where('boutique_id', $boutique->id)
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id','nom','reference','prix_unitaire','prix_gros','quantite_stock','unite','image']);

        return response()->json([
            'boutique' => [
                'id'        => $boutique->id,
                'nom'       => $boutique->nom,
                'ville'     => $boutique->ville,
                'pays'      => $boutique->pays,
                'telephone' => $boutique->telephone,
                'email'     => $boutique->email,
                'logo'      => $boutique->logo,
            ],
            'produits' => $produits,
        ]);
    }

    // ── Commande publique — le client passe une commande depuis le catalogue ──
    public function commandePublique(Request $request, $code)
    {
        $boutique = Boutique::where('code_invitation', $code)
            ->where('actif', true)
            ->firstOrFail();

        $request->validate([
            'client_nom'       => 'required|string|max:100',
            'client_telephone' => 'required|string|max:20',
            'client_quartier'  => 'nullable|string|max:100',
            'lien_localisation'=> 'nullable|string',
            'note_urgence'     => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.produit_id' => 'required|exists:produits,id',
            'items.*.quantite'   => 'required|integer|min:1',
        ]);

        // Créer la vente avec un caissiere_id fictif ou le premier vendeur de la boutique
        $vendeur = \App\Models\User::whereHas('role', fn($q)=>$q->where('nom','vendeur'))
            ->where('boutique_id', $boutique->id)
            ->first();

        if (!$vendeur) {
            return response()->json(['message' => 'Aucun vendeur disponible dans cette boutique'], 422);
        }

        // Calculer les items et montant
        $montant_total = 0;
        $items_data = [];
        foreach ($request->items as $item) {
            $produit = \App\Models\Produit::find($item['produit_id']);
            if (!$produit) continue;
            $sous_total = $produit->prix_unitaire * $item['quantite'];
            $montant_total += $sous_total;
            $items_data[] = [
                'produit_id'    => $produit->id,
                'quantite'      => $item['quantite'],
                'prix_unitaire' => $produit->prix_unitaire,
                'prix_vendeur'  => $produit->prix_unitaire,
                'remise'        => 0,
                'sous_total'    => $sous_total,
            ];
        }

        // Extraire coordonnées du lien si fourni
        $lat = null; $lng = null;
        if ($request->lien_localisation) {
            preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $request->lien_localisation, $m);
            if (count($m) >= 3) { $lat = $m[1]; $lng = $m[2]; }
            preg_match('/\?q=(-?\d+\.\d+),(-?\d+\.\d+)/', $request->lien_localisation, $m2);
            if (count($m2) >= 3) { $lat = $m2[1]; $lng = $m2[2]; }
        }

        $vente = \App\Models\Vente::create([
            'caissiere_id'      => $vendeur->id,
            'boutique_id'       => $boutique->id,
            'produit_id'        => $items_data[0]['produit_id'] ?? null,
            'client_nom'        => $request->client_nom,
            'client_telephone'  => $request->client_telephone,
            'client_quartier'   => $request->client_quartier,
            'note_urgence'      => $request->note_urgence,
            'montant_total'     => $montant_total,
            'statut'            => 'en_attente',
            'date_vente'        => now()->toDateString(),
            'vendeur_latitude'  => $lat,
            'vendeur_longitude' => $lng,
            'source'            => 'catalogue_public',
        ]);

        // Créer les items
        if (\Illuminate\Support\Facades\Schema::hasTable('vente_items')) {
            foreach ($items_data as $d) {
                \App\Models\VenteItem::create(array_merge($d, ['vente_id' => $vente->id]));
                \App\Models\Produit::find($d['produit_id'])?->decrement('quantite_stock', $d['quantite']);
            }
        }

        // Créer une livraison en attente
        \App\Models\Livraison::create([
            'vente_id'          => $vente->id,
            'boutique_id'       => $boutique->id,
            'statut'            => 'en_attente',
            'client_nom'        => $request->client_nom,
            'client_telephone'  => $request->client_telephone,
            'client_quartier'   => $request->client_quartier,
            'vendeur_latitude'  => $lat,
            'vendeur_longitude' => $lng,
            'date_livraison'    => now()->toDateString(),
        ]);

        return response()->json([
            'message'  => 'Commande enregistrée ! Un livreur vous contactera bientôt.',
            'vente_id' => $vente->id,
            'montant'  => $montant_total,
        ], 201);
    }

    // Lister les boutiques auxquelles l'utilisateur a accès
    public function mesBoutiques(Request $request)
    {
        $user = $request->user()->load('role');
        
        // Super admin voit toutes les boutiques
        if ($user->role->nom === 'super_admin') {
            return response()->json(Boutique::where('actif', true)->get());
        }

        // Autres utilisateurs voient uniquement leur boutique
        $boutique = Boutique::where('id', $user->boutique_id)->where('actif', true)->get();
        return response()->json($boutique);
    }
}