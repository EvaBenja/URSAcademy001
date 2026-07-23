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