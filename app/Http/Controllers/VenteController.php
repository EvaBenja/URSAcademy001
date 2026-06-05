<?php
namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\VenteItem;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VenteController extends Controller
{
    public function index()
    {
        $ventes = Vente::with(['produit','caissiere','items.produit'])
            ->orderByDesc('created_at')->get();
        return response()->json($ventes);
    }

    public function store(Request $request)
    {
        // Mode panier multiple via items[]
        if ($request->has('items') && is_array($request->items)) {
            $request->validate([
                'items'                     => 'required|array|min:1',
                'items.*.produit_id'        => 'required|exists:produits,id',
                'items.*.quantite'          => 'required|integer|min:1',
                'items.*.prix_vendeur'      => 'nullable|numeric|min:0',
                'items.*.remise'            => 'nullable|numeric|min:0',
                'date_vente'                => 'required|date',
                'zone_livraison'            => 'nullable|string',
                'notes'                     => 'nullable|string',
            ]);

            DB::beginTransaction();
            try {
                $montant_total = 0;
                $premier_produit_id = $request->items[0]['produit_id'];
                $items_data = [];

                foreach ($request->items as $item) {
                    $produit = Produit::findOrFail($item['produit_id']);
                    if ($produit->quantite_stock < $item['quantite']) {
                        DB::rollBack();
                        return response()->json(['message' => "Stock insuffisant pour {$produit->nom}. Disponible: {$produit->quantite_stock}"], 422);
                    }
                    $prix_vendeur = isset($item['prix_vendeur']) ? $item['prix_vendeur'] : $produit->prix_unitaire;
                    $remise       = isset($item['remise']) ? $item['remise'] : 0;
                    $sous_total   = ($prix_vendeur * $item['quantite']) - $remise;
                    $montant_total += $sous_total;
                    $items_data[]  = compact('produit','item','prix_vendeur','remise','sous_total');
                }

                $p0    = Produit::find($premier_produit_id);
                $vente = Vente::create([
                    'produit_id'     => $premier_produit_id,
                    'caissiere_id'   => $request->user()->id,
                    'quantite'       => array_sum(array_column($request->items, 'quantite')),
                    'prix_unitaire'  => $p0->prix_unitaire,
                    'prix_vendeur'   => $items_data[0]['prix_vendeur'],
                    'remise'         => 0,
                    'montant_total'  => $montant_total,
                    'date_vente'     => $request->date_vente,
                    'zone_livraison' => $request->zone_livraison,
                    'statut'         => 'en_attente',
                    'notes'          => $request->notes,
                ]);

                foreach ($items_data as $d) {
                    VenteItem::create([
                        'vente_id'      => $vente->id,
                        'produit_id'    => $d['item']['produit_id'],
                        'quantite'      => $d['item']['quantite'],
                        'prix_unitaire' => $d['produit']->prix_unitaire,
                        'prix_vendeur'  => $d['prix_vendeur'],
                        'remise'        => $d['remise'],
                        'sous_total'    => $d['sous_total'],
                    ]);
                    $d['produit']->decrement('quantite_stock', $d['item']['quantite']);
                }

                DB::commit();
                return response()->json($vente->load(['produit','caissiere','items.produit']), 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Erreur: '.$e->getMessage()], 500);
            }
        }

        // Mode classique (1 produit)
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
            return response()->json(['message' => 'Stock insuffisant. Disponible: '.$produit->quantite_stock], 422);
        }
        $prix_vendeur  = $request->prix_vendeur ?? $produit->prix_unitaire;
        $remise        = $request->remise ?? 0;
        $montant_total = ($prix_vendeur * $request->quantite) - $remise;

        $vente = Vente::create([
            'produit_id'     => $request->produit_id,
            'caissiere_id'   => $request->user()->id,
            'quantite'       => $request->quantite,
            'prix_unitaire'  => $produit->prix_unitaire,
            'prix_vendeur'   => $prix_vendeur,
            'remise'         => $remise,
            'montant_total'  => $montant_total,
            'date_vente'     => $request->date_vente,
            'zone_livraison' => $request->zone_livraison,
            'statut'         => 'en_attente',
            'notes'          => $request->notes,
        ]);
        $produit->decrement('quantite_stock', $request->quantite);
        return response()->json($vente->load(['produit','caissiere']), 201);
    }

    public function valider($id)
    {
        $vente = Vente::findOrFail($id);
        $vente->update(['statut' => 'validee']);
        return response()->json($vente->load(['produit','caissiere','items.produit']));
    }

    public function annuler($id)
    {
        $vente = Vente::findOrFail($id);
        if ($vente->items()->count() > 0) {
            foreach ($vente->items as $item) {
                $item->produit->increment('quantite_stock', $item->quantite);
            }
        } else {
            $vente->produit->increment('quantite_stock', $vente->quantite);
        }
        $vente->update(['statut' => 'annulee']);
        return response()->json(['message' => 'Vente annulée et stock remis']);
    }

    public function chiffreAffaires()
    {
        $total = Vente::where('statut', 'validee')->sum('montant_total');
        $count = Vente::where('statut', 'validee')->count();
        return response()->json([
            'total_ventes'      => $total,
            'nombre_ventes'     => $count,
            'ventes_en_attente' => Vente::where('statut', 'en_attente')->count(),
        ]);
    }

    // FIX: deux requêtes séparées pour éviter le bug with()+selectRaw+groupBy
    public function classementVendeurs()
    {
        // Étape 1: agrégation pure sans relation
        $aggregats = DB::table('ventes')
            ->where('statut', 'validee')
            ->select('caissiere_id', DB::raw('SUM(montant_total) as total'), DB::raw('COUNT(*) as nombre_ventes'))
            ->groupBy('caissiere_id')
            ->orderByDesc('total')
            ->get();

        // Étape 2: charger les users séparément
        $userIds = $aggregats->pluck('caissiere_id')->toArray();
        $users   = User::whereIn('id', $userIds)->get()->keyBy('id');

        $classement = $aggregats->values()->map(function ($item, $index) use ($users) {
            $user = $users->get($item->caissiere_id);
            $nom  = 'Inconnu';
            if ($user) {
                $nom = $user->prenom ? trim($user->prenom.' '.$user->nom) : $user->name;
            }
            return [
                'rang'          => $index + 1,
                'caissiere_id'  => $item->caissiere_id,
                'vendeur'       => $nom,
                'total'         => (float) $item->total,
                'nombre_ventes' => (int) $item->nombre_ventes,
            ];
        });

        return response()->json($classement);
    }

    public function stats()
    {
        return $this->chiffreAffaires();
    }

    public function chiffreAffairesParCaissiere()
    {
        $stats = DB::table('ventes')
            ->where('statut', 'validee')
            ->select('caissiere_id', DB::raw('SUM(montant_total) as total'))
            ->groupBy('caissiere_id')
            ->get();
        return response()->json($stats);
    }
}
