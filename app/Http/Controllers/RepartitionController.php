<?php

namespace App\Http\Controllers;

use App\Models\Repartition;
use App\Models\MediaBuying;
use App\Models\BudgetStock;
use App\Models\Benefice;
use App\Models\Commission;
use App\Models\Vente;
use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepartitionController extends Controller
{
    // Déclencher la répartition automatique après validation clôture
    public static function repartir(Livraison $livraison): void
    {
        $vente = $livraison->vente;
        if (!$vente) return;

        $items = $vente->items;
        $vendeurId = $vente->caissiere_id;
        $livreurId = $livraison->livreur_id;
        $boutiqueId = $vente->boutique_id;

        if ($items && $items->count() > 0) {
            foreach ($items as $item) {
                $produit = $item->produit;
                if (!$produit) continue;

                $rep = $produit->repartition((float) $item->sous_total, $item->quantite);
                self::enregistrerRepartition($rep, $vente, $livraison, $produit->id, $vendeurId, $livreurId, $boutiqueId);
            }
        } else {
            $produit = $vente->produit;
            $rep = $produit
                ? $produit->repartition((float) $vente->montant_total)
                : [
                    'montant_vente'      => (float) $vente->montant_total,
                    'commission_vendeur' => 0,
                    'commission_livreur' => 0,
                    'media_buying'       => 0,
                    'budget_stock'       => 0,
                    'benefice'           => 0,
                    'total_reparti'      => 0,
                ];
            self::enregistrerRepartition($rep, $vente, $livraison, $produit?->id, $vendeurId, $livreurId, $boutiqueId);
        }
    }

    private static function enregistrerRepartition(array $rep, Vente $vente, Livraison $livraison, ?int $produitId, ?int $vendeurId, ?int $livreurId, ?int $boutiqueId): void
    {
        DB::transaction(function () use ($rep, $vente, $livraison, $produitId, $vendeurId, $livreurId, $boutiqueId) {

            // 1. Enregistrer la répartition globale
            Repartition::create([
                'vente_id'           => $vente->id,
                'livraison_id'       => $livraison->id,
                'produit_id'         => $produitId,
                'vendeur_id'         => $vendeurId,
                'livreur_id'         => $livreurId,
                'boutique_id'        => $boutiqueId,
                'montant_vente'      => $rep['montant_vente'],
                'commission_vendeur' => $rep['commission_vendeur'],
                'commission_livreur' => $rep['commission_livreur'],
                'media_buying'       => $rep['media_buying'],
                'budget_stock'       => $rep['budget_stock'],
                'benefice'           => $rep['benefice'],
                'total_reparti'      => $rep['total_reparti'],
                'traitee'            => true,
                'traitee_le'         => now(),
            ]);

            // 2. Commission vendeur
            if ($rep['commission_vendeur'] > 0 && $vendeurId) {
                Commission::create([
                    'user_id'                => $vendeurId,
                    'vente_id'               => $vente->id,
                    'produit_id'             => $produitId,
                    'montant_vente'          => $rep['montant_vente'],
                    'commission_fixe'        => 0,
                    'commission_pourcentage' => 0,
                    'montant_commission'     => $rep['commission_vendeur'],
                    'statut'                 => 'validee',
                ]);
            }

            // 3. Commission livreur
            if ($rep['commission_livreur'] > 0 && $livreurId) {
                Commission::create([
                    'user_id'                => $livreurId,
                    'vente_id'               => $vente->id,
                    'produit_id'             => $produitId,
                    'montant_vente'          => $rep['montant_vente'],
                    'commission_fixe'        => 0,
                    'commission_pourcentage' => 0,
                    'montant_commission'     => $rep['commission_livreur'],
                    'statut'                 => 'validee',
                ]);
            }

            // 4. Media Buying
            if ($rep['media_buying'] > 0) {
                $soldeMB = MediaBuying::sum('montant') - MediaBuying::where('type','debit')->sum('montant');
                MediaBuying::create([
                    'boutique_id' => $boutiqueId,
                    'vendeur_id'  => $vendeurId,
                    'vente_id'    => $vente->id,
                    'type'        => 'credit',
                    'montant'     => $rep['media_buying'],
                    'description' => 'Budget pub vente #' . $vente->id,
                    'solde_apres' => $soldeMB + $rep['media_buying'],
                ]);
            }

            // 5. Budget Stock
            if ($rep['budget_stock'] > 0) {
                $soldeBS = BudgetStock::where('type','credit')->sum('montant') - BudgetStock::where('type','debit')->sum('montant');
                BudgetStock::create([
                    'boutique_id' => $boutiqueId,
                    'vente_id'    => $vente->id,
                    'type'        => 'credit',
                    'montant'     => $rep['budget_stock'],
                    'description' => 'Réapprovisionnement stock vente #' . $vente->id,
                    'solde_apres' => $soldeBS + $rep['budget_stock'],
                ]);
            }

            // 6. Bénéfice
            Benefice::create([
                'boutique_id'        => $boutiqueId,
                'vente_id'           => $vente->id,
                'livraison_id'       => $livraison->id,
                'montant_vente'      => $rep['montant_vente'],
                'commission_vendeur' => $rep['commission_vendeur'],
                'commission_livreur' => $rep['commission_livreur'],
                'media_buying'       => $rep['media_buying'],
                'budget_stock'       => $rep['budget_stock'],
                'benefice_net'       => $rep['benefice'],
                'date_vente'         => $vente->date_vente,
            ]);
        });
    }

    // ── Stats Media Buying (media buyer + super admin) ──
    public function mediaBuying(Request $request)
    {
        $solde = MediaBuying::where('type','credit')->sum('montant')
               - MediaBuying::where('type','debit')->sum('montant');

        $historique = MediaBuying::with(['vendeur','traitePar'])
            ->orderByDesc('created_at')
            ->get();

        $parVendeur = MediaBuying::where('type','credit')
            ->with('vendeur')
            ->get()
            ->groupBy('vendeur_id')
            ->map(function($items) {
                $vendeur = $items->first()->vendeur;
                return [
                    'vendeur'          => $vendeur?->name ?? 'Inconnu',
                    'budget_cumule'    => $items->sum('montant'),
                ];
            })->values();

        return response()->json([
            'solde_global' => $solde,
            'historique'   => $historique,
            'par_vendeur'  => $parVendeur,
        ]);
    }

    // Déduire du budget media buying (media buyer)
    public function deduireMediaBuying(Request $request)
    {
        $request->validate([
            'montant'     => 'required|numeric|min:1',
            'description' => 'required|string',
        ]);

        $solde = MediaBuying::where('type','credit')->sum('montant')
               - MediaBuying::where('type','debit')->sum('montant');

        if ($request->montant > $solde) {
            return response()->json(['message' => 'Solde insuffisant'], 422);
        }

        MediaBuying::create([
            'traite_par'  => $request->user()->id,
            'type'        => 'debit',
            'montant'     => $request->montant,
            'description' => $request->description,
            'solde_apres' => $solde - $request->montant,
        ]);

        return response()->json(['message' => 'Déduction effectuée', 'solde' => $solde - $request->montant]);
    }

    // ── Stats Budget Stock (super admin) ──
    public function budgetStock()
    {
        $solde = BudgetStock::where('type','credit')->sum('montant')
               - BudgetStock::where('type','debit')->sum('montant');

        return response()->json([
            'solde'      => $solde,
            'entrees'    => BudgetStock::where('type','credit')->sum('montant'),
            'sorties'    => BudgetStock::where('type','debit')->sum('montant'),
            'historique' => BudgetStock::with('traitePar')->orderByDesc('created_at')->get(),
        ]);
    }

    // Déduire du budget stock (super admin)
    public function deduireBudgetStock(Request $request)
    {
        $request->validate([
            'montant'     => 'required|numeric|min:1',
            'description' => 'required|string',
        ]);

        $solde = BudgetStock::where('type','credit')->sum('montant')
               - BudgetStock::where('type','debit')->sum('montant');

        BudgetStock::create([
            'traite_par'  => $request->user()->id,
            'type'        => 'debit',
            'montant'     => $request->montant,
            'description' => $request->description,
            'solde_apres' => $solde - $request->montant,
        ]);

        return response()->json(['message' => 'Déduction effectuée', 'solde' => $solde - $request->montant]);
    }

    // ── Bénéfices (super admin) ──
    public function benefices(Request $request)
    {
        $query = Benefice::orderByDesc('date_vente');

        if ($request->periode === 'jour') {
            $query->whereDate('date_vente', today());
        } elseif ($request->periode === 'semaine') {
            $query->whereBetween('date_vente', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($request->periode === 'mois') {
            $query->whereMonth('date_vente', now()->month)->whereYear('date_vente', now()->year);
        }

        $benefices = $query->get();

        return response()->json([
            'total'         => $benefices->sum('benefice_net'),
            'nb_ventes'     => $benefices->count(),
            'par_jour'      => $benefices->groupBy('date_vente')->map(fn($b, $d) => [
                'date'    => $d,
                'total'   => $b->sum('benefice_net'),
                'nb'      => $b->count(),
            ])->values(),
            'historique'    => $benefices,
        ]);
    }
}