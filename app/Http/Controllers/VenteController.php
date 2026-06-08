<?php
namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\VenteItem;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VenteController extends Controller
{
    public function index()
    {
        $hasItems = \Illuminate\Support\Facades\Schema::hasTable('vente_items');
        $with = $hasItems ? ['produit','caissiere','items.produit'] : ['produit','caissiere'];
        $ventes = Vente::with($with)->orderByDesc('created_at')->get();
        return response()->json($ventes);
    }

    public function store(Request $request)
    {
        // ── Mode panier multiple (items[]) ──
        if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {

            $request->validate([
                'items'                => 'required|array|min:1',
                'items.*.produit_id'   => 'required|exists:produits,id',
                'items.*.quantite'     => 'required|integer|min:1',
                'items.*.prix_vendeur' => 'nullable|numeric|min:0',
                'items.*.remise'       => 'nullable|numeric|min:0',
                'date_vente'           => 'required|date',
                'zone_livraison'       => 'nullable|string',
                'notes'                => 'nullable|string',
            ]);

            // Vérifier les stocks d'abord
            foreach ($request->items as $item) {
                $produit = Produit::findOrFail($item['produit_id']);
                if ($produit->quantite_stock < $item['quantite']) {
                    return response()->json([
                        'message' => "Stock insuffisant pour \"{$produit->nom}\". Disponible : {$produit->quantite_stock}"
                    ], 422);
                }
            }

            // Calculer le montant total
            $montant_total = 0;
            $items_data    = [];
            foreach ($request->items as $item) {
                $produit      = Produit::find($item['produit_id']);
                $prix_vendeur = isset($item['prix_vendeur']) && $item['prix_vendeur'] > 0
                    ? (float)$item['prix_vendeur']
                    : (float)$produit->prix_unitaire;
                $remise       = isset($item['remise']) ? (float)$item['remise'] : 0;
                $sous_total   = ($prix_vendeur * (int)$item['quantite']) - $remise;
                $montant_total += $sous_total;
                $items_data[]  = [
                    'produit'      => $produit,
                    'produit_id'   => $item['produit_id'],
                    'quantite'     => (int)$item['quantite'],
                    'prix_vendeur' => $prix_vendeur,
                    'remise'       => $remise,
                    'sous_total'   => $sous_total,
                ];
            }

            // Créer la vente principale (premier produit pour compatibilité)
            $premier = $items_data[0];
            $vente = Vente::create([
                'produit_id'     => $premier['produit_id'],
                'caissiere_id'   => $request->user()->id,
                'quantite'       => array_sum(array_column($items_data, 'quantite')),
                'prix_unitaire'  => (float)$premier['produit']->prix_unitaire,
                'prix_vendeur'   => $premier['prix_vendeur'],
                'remise'         => 0,
                'montant_total'  => $montant_total,
                'date_vente'     => $request->date_vente,
                'zone_livraison' => $request->zone_livraison,
                'statut'         => 'en_attente',
                'notes'          => $request->notes,
            ]);

            // Créer les items détaillés (seulement si table existe)
            if (Schema::hasTable('vente_items')) {
                foreach ($items_data as $d) {
                    VenteItem::create([
                        'vente_id'      => $vente->id,
                        'produit_id'    => $d['produit_id'],
                        'quantite'      => $d['quantite'],
                        'prix_unitaire' => (float)$d['produit']->prix_unitaire,
                        'prix_vendeur'  => $d['prix_vendeur'],
                        'remise'        => $d['remise'],
                        'sous_total'    => $d['sous_total'],
                    ]);
                    // Décrémenter le stock
                    $d['produit']->decrement('quantite_stock', $d['quantite']);
                }
            } else {
                // Fallback sans vente_items
                foreach ($items_data as $d) {
                    $d['produit']->decrement('quantite_stock', $d['quantite']);
                }
            }

            return response()->json(
                $vente->load(['produit', 'caissiere', 'items.produit']),
                201
            );
        }

        // ── Mode classique (1 produit) ──
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
        if ($produit->quantite_stock < $request->quantite) {
            return response()->json([
                'message' => "Stock insuffisant pour \"{$produit->nom}\". Disponible : {$produit->quantite_stock}"
            ], 422);
        }

        $prix_vendeur  = $request->filled('prix_vendeur') ? (float)$request->prix_vendeur : (float)$produit->prix_unitaire;
        $remise        = $request->filled('remise') ? (float)$request->remise : 0;
        $montant_total = ($prix_vendeur * (int)$request->quantite) - $remise;

        $vente = Vente::create([
            'produit_id'     => $request->produit_id,
            'caissiere_id'   => $request->user()->id,
            'quantite'       => (int)$request->quantite,
            'prix_unitaire'  => (float)$produit->prix_unitaire,
            'prix_vendeur'   => $prix_vendeur,
            'remise'         => $remise,
            'montant_total'  => $montant_total,
            'date_vente'     => $request->date_vente,
            'zone_livraison' => $request->zone_livraison,
            'statut'         => 'en_attente',
            'notes'          => $request->notes,
        ]);

        $produit->decrement('quantite_stock', $request->quantite);

        return response()->json($vente->load(['produit', 'caissiere']), 201);
    }

    public function valider($id)
    {
        $vente = Vente::findOrFail($id);
        if ($vente->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette vente ne peut plus être modifiée'], 422);
        }
        $vente->update(['statut' => 'validee']);
        $hasItems = \Illuminate\Support\Facades\Schema::hasTable('vente_items');
        $with = $hasItems ? ['produit','caissiere','items.produit'] : ['produit','caissiere'];
        return response()->json($vente->load($with));
    }

    public function annuler($id)
    {
        $vente = Vente::findOrFail($id);
        if ($vente->statut === 'annulee') {
            return response()->json(['message' => 'Vente déjà annulée'], 422);
        }
        // Remettre le stock
        if (Schema::hasTable('vente_items') && $vente->items()->count() > 0) {
            foreach ($vente->items as $item) {
                Produit::where('id', $item->produit_id)
                    ->increment('quantite_stock', $item->quantite);
            }
        } else {
            Produit::where('id', $vente->produit_id)
                ->increment('quantite_stock', $vente->quantite);
        }
        $vente->update(['statut' => 'annulee']);
        return response()->json(['message' => 'Vente annulée et stock remis']);
    }

    public function chiffreAffaires()
    {
        return response()->json([
            'total_ventes'      => (float) Vente::where('statut', 'validee')->sum('montant_total'),
            'nombre_ventes'     => Vente::where('statut', 'validee')->count(),
            'ventes_en_attente' => Vente::where('statut', 'en_attente')->count(),
        ]);
    }

    public function stats()
    {
        return $this->chiffreAffaires();
    }

    public function classementVendeurs()
    {
        // Toutes les ventes soumises (en_attente + validee) pour le classement en temps réel
        $aggregats = DB::table('ventes')
            ->whereIn('statut', ['en_attente', 'validee'])
            ->select(
                'caissiere_id',
                DB::raw('SUM(montant_total) as total'),
                DB::raw('COUNT(*) as nombre_ventes')
            )
            ->groupBy('caissiere_id')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $userIds = $aggregats->pluck('caissiere_id')->toArray();
        $users   = User::whereIn('id', $userIds)->get()->keyBy('id');

        return response()->json(
            $aggregats->values()->map(function ($item, $index) use ($users) {
                $user = $users->get($item->caissiere_id);
                $nom  = 'Inconnu';
                if ($user) {
                    $nom = $user->prenom
                        ? trim($user->prenom . ' ' . $user->nom)
                        : $user->name;
                }
                return [
                    'rang'          => $index + 1,
                    'caissiere_id'  => $item->caissiere_id,
                    'vendeur'       => $nom,
                    'total'         => (float) $item->total,
                    'nombre_ventes' => (int) $item->nombre_ventes,
                ];
            })
        );
    }

    public function chiffreAffairesParCaissiere()
    {
        return response()->json(
            DB::table('ventes')
                ->whereIn('statut', ['en_attente', 'validee'])
                ->select('caissiere_id', DB::raw('SUM(montant_total) as total'))
                ->groupBy('caissiere_id')
                ->get()
        );
    }
}
