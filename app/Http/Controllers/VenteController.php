<?php
namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\VenteItem;
use App\Models\Produit;
use App\Models\User;
use App\Models\Livraison;
use App\Models\LivraisonProduit;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VenteController extends Controller
{
    public function index()
    {
        $with = Schema::hasTable('vente_items')
            ? ['produit','caissiere','items.produit','livraison.livreur']
            : ['produit','caissiere','livraison.livreur'];
        return response()->json(Vente::with($with)->orderByDesc('created_at')->get());
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
                'client_nom'           => 'nullable|string',
                'client_telephone'     => 'nullable|string',
                'client_quartier'      => 'nullable|string',
            ]);

            // Vérifier stocks
            foreach ($request->items as $item) {
                $p = Produit::findOrFail($item['produit_id']);
                if ($p->quantite_stock < $item['quantite']) {
                    return response()->json(['message' => "Stock insuffisant pour \"{$p->nom}\". Dispo: {$p->quantite_stock}"], 422);
                }
            }

            $montant_total  = 0;
            $remise_total   = 0;
            $items_data     = [];
            foreach ($request->items as $item) {
                $p            = Produit::find($item['produit_id']);
                $prix_vendeur = isset($item['prix_vendeur']) && $item['prix_vendeur'] > 0 ? (float)$item['prix_vendeur'] : (float)$p->prix_unitaire;
                $remise       = isset($item['remise']) ? (float)$item['remise'] : 0;
                $sous_total   = ($prix_vendeur * (int)$item['quantite']) - $remise;
                $montant_total += $sous_total;
                $remise_total  += $remise;
                $items_data[]  = compact('p','item','prix_vendeur','remise','sous_total');
            }

            $premier = $items_data[0];
            $data = [
                'produit_id'    => $premier['item']['produit_id'],
                'caissiere_id'  => $request->user()->id,
                'quantite'      => array_sum(array_column($request->items,'quantite')),
                'prix_unitaire' => (float)$premier['p']->prix_unitaire,
                'prix_vendeur'  => $premier['prix_vendeur'],
                'remise'        => $remise_total,  // somme réelle des remises
                'montant_total' => $montant_total,
                'date_vente'    => $request->date_vente,
                'zone_livraison'=> $request->zone_livraison,
                'statut'        => 'en_attente',
                'notes'         => $request->notes,
            ];
            if (Schema::hasColumn('ventes','client_nom')) {
                $data['client_nom']       = $request->client_nom;
                $data['client_telephone'] = $request->client_telephone;
                $data['client_quartier']  = $request->client_quartier;
            }
            if (Schema::hasColumn('ventes','note_urgence')) {
                $data['note_urgence']  = $request->note_urgence;
                $data['est_expedition']= (bool)$request->est_expedition;
            }
            if (Schema::hasColumn('ventes','vendeur_latitude')) {
                $data['vendeur_latitude']  = $request->vendeur_latitude;
                $data['vendeur_longitude'] = $request->vendeur_longitude;
            }
            $vente = Vente::create($data);

            if (Schema::hasTable('vente_items')) {
                foreach ($items_data as $idx => $d) {
                    VenteItem::create([
                        'vente_id'      => $vente->id,
                        'produit_id'    => $d['item']['produit_id'],
                        'quantite'      => $d['item']['quantite'],
                        'prix_unitaire' => (float)$d['p']->prix_unitaire,
                        'prix_vendeur'  => $d['prix_vendeur'],
                        'remise'        => $d['remise'],
                        'sous_total'    => $d['sous_total'],
                        'couleur'       => $d['item']['couleur'] ?? null,
                    ]);
                    $d['p']->decrement('quantite_stock', $d['item']['quantite']);
                }
            }

            // ── Créer immédiatement la course/livraison — visible par tous les livreurs ──
            $livraison = $this->creerLivraisonPourVente($vente, $items_data);

            $with = Schema::hasTable('vente_items') ? ['produit','caissiere','items.produit','livraison.livreur'] : ['produit','caissiere','livraison.livreur'];
            return response()->json($vente->load($with), 201);
        }

        // ── Mode classique (1 produit) ──
        $request->validate([
            'produit_id'       => 'required|exists:produits,id',
            'quantite'         => 'required|integer|min:1',
            'date_vente'       => 'required|date',
            'prix_vendeur'     => 'nullable|numeric|min:0',
            'remise'           => 'nullable|numeric|min:0',
            'zone_livraison'   => 'nullable|string',
            'notes'            => 'nullable|string',
            'client_nom'       => 'nullable|string',
            'client_telephone' => 'nullable|string',
            'client_quartier'  => 'nullable|string',
        ]);

        $produit = Produit::findOrFail($request->produit_id);
        if ($produit->quantite_stock < $request->quantite) {
            return response()->json(['message' => "Stock insuffisant. Dispo: {$produit->quantite_stock}"], 422);
        }

        $prix_vendeur  = $request->filled('prix_vendeur') ? (float)$request->prix_vendeur : (float)$produit->prix_unitaire;
        $remise        = $request->filled('remise') ? (float)$request->remise : 0;
        $montant_total = ($prix_vendeur * (int)$request->quantite) - $remise;

        $data = [
            'produit_id'    => $request->produit_id,
            'caissiere_id'  => $request->user()->id,
            'quantite'      => (int)$request->quantite,
            'prix_unitaire' => (float)$produit->prix_unitaire,
            'prix_vendeur'  => $prix_vendeur,
            'remise'        => $remise,
            'montant_total' => $montant_total,
            'date_vente'    => $request->date_vente,
            'zone_livraison'=> $request->zone_livraison,
            'statut'        => 'en_attente',
            'notes'         => $request->notes,
        ];
        if (Schema::hasColumn('ventes','client_nom')) {
            $data['client_nom']       = $request->client_nom;
            $data['client_telephone'] = $request->client_telephone;
            $data['client_quartier']  = $request->client_quartier;
        }
        $vente = Vente::create($data);
        $produit->decrement('quantite_stock', $request->quantite);

        // ── Créer immédiatement la course/livraison — visible par tous les livreurs ──
        $this->creerLivraisonPourVente($vente, [
            ['p' => $produit, 'item' => ['produit_id' => $produit->id, 'quantite' => $request->quantite]],
        ]);

        return response()->json($vente->load(['produit','caissiere','livraison.livreur']), 201);
    }

    // ── Crée la livraison/course liée à une vente, dès sa soumission ──
    // Visible immédiatement par tous les livreurs (sans livreur assigné).
    private function creerLivraisonPourVente(Vente $vente, array $itemsData): Livraison
    {
        $livData = [
            'statut'         => 'en_attente',
            'date_livraison' => $vente->date_vente,
            'zone_livraison' => $vente->zone_livraison,
            'notes'          => $vente->notes,
        ];
        if (Schema::hasColumn('livraisons','vente_id'))        $livData['vente_id']         = $vente->id;
        if (Schema::hasColumn('livraisons','client_nom'))      $livData['client_nom']       = $vente->client_nom ?? null;
        if (Schema::hasColumn('livraisons','client_telephone'))$livData['client_telephone'] = $vente->client_telephone ?? null;
        if (Schema::hasColumn('livraisons','client_quartier')) $livData['client_quartier']  = $vente->client_quartier ?? null;
        if (Schema::hasColumn('livraisons','vendeur_latitude'))  $livData['vendeur_latitude']  = $vente->vendeur_latitude ?? null;
        if (Schema::hasColumn('livraisons','vendeur_longitude')) $livData['vendeur_longitude'] = $vente->vendeur_longitude ?? null;

        // Géocodage automatique du quartier client (en arrière-plan, transparent)
        // pour permettre l'assignation au livreur le plus proche.
        $adresseRecherche = $vente->client_quartier ?? $vente->zone_livraison ?? null;
        if ($adresseRecherche && Schema::hasColumn('livraisons','client_latitude')) {
            $coords = (new GeocodingService())->geocode($adresseRecherche);
            if ($coords) {
                $livData['client_latitude']  = $coords['lat'];
                $livData['client_longitude'] = $coords['lng'];
            }
        }

        $livraison = Livraison::create($livData);

        // Lier les produits de la vente à la livraison pour la clôture (cocher livré/non livré)
        if (Schema::hasTable('livraison_produits')) {
            foreach ($itemsData as $d) {
                LivraisonProduit::create([
                    'livraison_id' => $livraison->id,
                    'produit_id'   => $d['item']['produit_id'],
                    'quantite'     => $d['item']['quantite'],
                ]);
            }
        }

        return $livraison;
    }

    public function annuler(Request $request, $id)
    {
        $request->validate(['motif' => 'required|string|min:3']);

        $vente = Vente::findOrFail($id);
        if ($vente->statut === 'annulee') {
            return response()->json(['message' => 'Vente déjà annulée'], 422);
        }
        if (Schema::hasTable('vente_items') && $vente->items()->count() > 0) {
            foreach ($vente->items as $item) {
                Produit::where('id', $item->produit_id)->increment('quantite_stock', $item->quantite);
            }
        } else {
            Produit::where('id', $vente->produit_id)->increment('quantite_stock', $vente->quantite);
        }

        $updateData = ['statut' => 'annulee'];
        if (Schema::hasColumn('ventes','motif_annulation')) {
            $updateData['motif_annulation'] = $request->motif;
        }
        $vente->update($updateData);

        return response()->json(['message' => 'Vente refusée et stock remis']);
    }

    // Suppression définitive par super_admin — aucune restriction de statut
    public function supprimer(Request $request, $id)
    {
        $vente = Vente::with(['livraison','items'])->findOrFail($id);

        // Restituer le stock
        if (Schema::hasTable('vente_items') && $vente->items->count() > 0) {
            foreach ($vente->items as $item) {
                Produit::where('id', $item->produit_id)->increment('quantite_stock', $item->quantite);
            }
        } elseif ($vente->produit_id) {
            Produit::where('id', $vente->produit_id)->increment('quantite_stock', $vente->quantite ?? 1);
        }

        // Supprimer la livraison liée si elle existe
        if ($vente->livraison) {
            $vente->livraison->delete();
        }

        // Supprimer les items et la vente
        if (Schema::hasTable('vente_items')) {
            $vente->items()->delete();
        }
        $vente->delete();

        return response()->json(['message' => 'Vente supprimée définitivement']);
    }

    // Modifier une vente — uniquement si la livraison n'est pas encore en cours
    public function update(Request $request, $id)
    {
        $vente = Vente::with('livraison')->findOrFail($id);

        // Vérification : pas encore prise par un livreur
        if ($vente->livraison && in_array($vente->livraison->statut, ['en_cours','livree_attente_validation','terminee'])) {
            return response()->json(['message' => 'Impossible — un livreur a déjà pris cette course en charge'], 422);
        }

        $request->validate([
            'zone_livraison'   => 'nullable|string',
            'notes'            => 'nullable|string',
            'note_urgence'     => 'nullable|string',
            'est_expedition'   => 'nullable|boolean',
            'client_nom'       => 'nullable|string',
            'client_telephone' => 'nullable|string',
            'client_quartier'  => 'nullable|string',
            'vendeur_latitude' => 'nullable|numeric',
            'vendeur_longitude'=> 'nullable|numeric',
        ]);

        $data = array_filter([
            'zone_livraison'    => $request->zone_livraison,
            'notes'             => $request->notes,
            'note_urgence'      => $request->note_urgence,
            'est_expedition'    => $request->has('est_expedition') ? (bool)$request->est_expedition : null,
            'client_nom'        => $request->client_nom,
            'client_telephone'  => $request->client_telephone,
            'client_quartier'   => $request->client_quartier,
            'vendeur_latitude'  => $request->vendeur_latitude,
            'vendeur_longitude' => $request->vendeur_longitude,
        ], fn($v) => $v !== null);

        $vente->update($data);

        // Sync la zone sur la livraison associée si elle existe
        if ($vente->livraison && $request->zone_livraison) {
            $vente->livraison->update(['zone_livraison' => $request->zone_livraison]);
        }

        return response()->json([
            'message' => 'Vente modifiée avec succès',
            'vente'   => $vente->fresh(['items.produit','livraison','caissiere']),
        ]);
    }

    public function chiffreAffaires()
    {
        // CA RÉEL = ventes dont la livraison est terminée (clôturée par le gestionnaire)
        $caReel = Vente::whereHas('livraison', fn($q) => $q->where('statut','terminee'))
            ->sum('montant_total');

        // CA EN COURS = ventes soumises dont la livraison n'est pas encore terminée
        $caEnCours = Vente::where('statut','!=','annulee')
            ->whereDoesntHave('livraison', fn($q) => $q->where('statut','terminee'))
            ->sum('montant_total');

        return response()->json([
            'total_ventes'        => (float) $caReel,
            'ca_en_cours'         => (float) $caEnCours,
            'nombre_ventes'       => Vente::where('statut','!=','annulee')->count(),
            'ventes_annulees'     => Vente::where('statut','annulee')->count(),
            'livraisons_terminees'=> \App\Models\Livraison::where('statut','terminee')->count(),
        ]);
    }

    public function stats() { return $this->chiffreAffaires(); }

    public function classementVendeurs()
    {
        // Classement basé sur les ventes dont la livraison est terminée (CA réel)
        $aggregats = DB::table('ventes')
            ->join('livraisons', 'livraisons.vente_id', '=', 'ventes.id')
            ->where('livraisons.statut', 'terminee')
            ->select('ventes.caissiere_id',
                DB::raw('SUM(ventes.montant_total) as total'),
                DB::raw('COUNT(ventes.id) as nombre_ventes'))
            ->groupBy('ventes.caissiere_id')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $users = User::whereIn('id', $aggregats->pluck('caissiere_id'))->get()->keyBy('id');
        return response()->json($aggregats->values()->map(function($item, $i) use ($users) {
            $u = $users->get($item->caissiere_id);
            return [
                'rang'          => $i + 1,
                'caissiere_id'  => $item->caissiere_id,
                'vendeur'       => $u ? ($u->prenom ? trim($u->prenom.' '.$u->nom) : $u->name) : 'Inconnu',
                'total'         => (float)$item->total,
                'nombre_ventes' => (int)$item->nombre_ventes,
            ];
        }));
    }

    public function chiffreAffairesParCaissiere()
    {
        // CA réel par vendeur = ventes terminées seulement
        return response()->json(DB::table('ventes')
            ->join('livraisons', 'livraisons.vente_id', '=', 'ventes.id')
            ->where('livraisons.statut', 'terminee')
            ->select('ventes.caissiere_id', DB::raw('SUM(ventes.montant_total) as total'))
            ->groupBy('ventes.caissiere_id')
            ->get());
    }

    // Historique CA avec granularité jour/semaine/mois/année
    public function historiqueCa(Request $request)
    {
        $periode = $request->get('periode', 'mois');

        switch ($periode) {
            case 'jour':
                $debut   = now()->startOfDay();
                $fin     = now()->endOfDay();
                $groupBy = 'HOUR(ventes.date_vente)';
                $select  = 'HOUR(ventes.date_vente) as label';
                break;
            case 'semaine':
                $debut   = now()->startOfWeek();
                $fin     = now()->endOfWeek();
                $groupBy = 'DATE(ventes.date_vente)';
                $select  = 'DATE(ventes.date_vente) as label';
                break;
            case 'mois':
                $debut   = now()->startOfMonth();
                $fin     = now()->endOfMonth();
                $groupBy = 'DATE(ventes.date_vente)';
                $select  = 'DATE(ventes.date_vente) as label';
                break;
            case 'annee':
                $debut   = now()->startOfYear();
                $fin     = now()->endOfYear();
                $groupBy = 'MONTH(ventes.date_vente)';
                $select  = 'MONTH(ventes.date_vente) as label';
                break;
            default:
                $debut   = now()->subDays(29)->startOfDay();
                $fin     = now()->endOfDay();
                $groupBy = 'DATE(ventes.date_vente)';
                $select  = 'DATE(ventes.date_vente) as label';
        }

        $historique = DB::table('ventes')
            ->join('livraisons', 'livraisons.vente_id', '=', 'ventes.id')
            ->where('livraisons.statut', 'terminee')
            ->whereBetween('ventes.date_vente', [$debut, $fin])
            ->selectRaw("$select, SUM(ventes.montant_total) as ca_reel, COUNT(ventes.id) as nb_ventes, SUM(ventes.remise) as total_remises")
            ->groupByRaw($groupBy)
            ->orderByRaw($groupBy)
            ->get();

        $totaux = DB::table('ventes')
            ->join('livraisons', 'livraisons.vente_id', '=', 'ventes.id')
            ->where('livraisons.statut', 'terminee')
            ->whereBetween('ventes.date_vente', [$debut, $fin])
            ->selectRaw('SUM(ventes.montant_total) as ca_total, COUNT(ventes.id) as nb_ventes, SUM(ventes.remise) as total_remises')
            ->first();

        $duree     = (int) $debut->diffInDays($fin) + 1;
        $debutPrec = $debut->copy()->subDays($duree);
        $finPrec   = $fin->copy()->subDays($duree);

        $totauxPrec = DB::table('ventes')
            ->join('livraisons', 'livraisons.vente_id', '=', 'ventes.id')
            ->where('livraisons.statut', 'terminee')
            ->whereBetween('ventes.date_vente', [$debutPrec, $finPrec])
            ->selectRaw('SUM(ventes.montant_total) as ca_total, COUNT(ventes.id) as nb_ventes')
            ->first();

        $evolution = 0;
        if ($totauxPrec && $totauxPrec->ca_total > 0) {
            $evolution = round((((float)$totaux->ca_total - (float)$totauxPrec->ca_total) / (float)$totauxPrec->ca_total) * 100, 1);
        }

        return response()->json([
            'historique'   => $historique,
            'totaux'       => $totaux,
            'evolution'    => $evolution,
            'periode'      => $periode,
            'debut'        => $debut->toDateString(),
            'fin'          => $fin->toDateString(),
            'periode_prec' => ['ca' => (float)($totauxPrec->ca_total ?? 0), 'nb' => (int)($totauxPrec->nb_ventes ?? 0)],
        ]);
    }


    // Comptabilité journalière — pour le rôle comptable
    public function comptabiliteJournalier(Request $request)
    {
        $date = $request->get('date', now()->toDateString());

        // Toutes les ventes du jour (soumises)
        $ventesJour = Vente::with(['caissiere','items.produit','livraison'])
            ->whereDate('date_vente', $date)
            ->where('statut','!=','annulee')
            ->get();

        // CA réel du jour (livraisons terminées)
        $caReel = $ventesJour->filter(fn($v) => $v->livraison?->statut === 'terminee')->sum('montant_total');
        $caEnCours = $ventesJour->filter(fn($v) => $v->livraison?->statut !== 'terminee')->sum('montant_total');

        // Par vendeur
        $parVendeur = $ventesJour->groupBy('caissiere_id')->map(function($ventes, $id) {
            $v0 = $ventes->first();
            $nom = $v0->caissiere ? trim(($v0->caissiere->prenom ?? $v0->caissiere->name ?? '').' '.($v0->caissiere->nom ?? '')) : 'Inconnu';
            return [
                'vendeur'      => $nom,
                'telephone'    => $v0->caissiere?->telephone ?? '—',
                'nb_ventes'    => $ventes->count(),
                'ca_soumis'    => (float)$ventes->sum('montant_total'),
                'ca_reel'      => (float)$ventes->filter(fn($v) => $v->livraison?->statut === 'terminee')->sum('montant_total'),
                'total_remises'=> (float)$ventes->sum('remise'),
                'ventes'       => $ventes->map(fn($v) => [
                    'id'           => $v->id,
                    'heure'        => $v->created_at?->format('H:i'),
                    'montant'      => (float)$v->montant_total,
                    'remise'       => (float)$v->remise,
                    'statut_liv'   => $v->livraison?->statut ?? 'sans_livraison',
                    'produits'     => $v->items->map(fn($it) => [
                        'nom'      => $it->produit?->nom ?? '—',
                        'quantite' => $it->quantite,
                        'prix'     => (float)$it->prix_vendeur,
                        'remise'   => (float)$it->remise,
                        'couleur'  => $it->couleur,
                        'sous_total'=> (float)$it->sous_total,
                    ]),
                ]),
            ];
        })->values();

        return response()->json([
            'date'        => $date,
            'ca_reel'     => (float)$caReel,
            'ca_en_cours' => (float)$caEnCours,
            'nb_ventes'   => $ventesJour->count(),
            'nb_annulees' => Vente::whereDate('date_vente',$date)->where('statut','annulee')->count(),
            'par_vendeur' => $parVendeur,
        ]);
    }

}
