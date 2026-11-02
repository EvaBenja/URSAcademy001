<?php

namespace App\Http\Controllers;

use App\Models\BilanLivreur;
use App\Models\BilanLigne;
use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bilan journalier d'un livreur.
 *
 * IMPORTANT : le bilan est un document de CONTRÔLE, distinct de la clôture
 * commerciale d'une vente (LivraisonController::validerCloture). Il ne
 * déduit ni ne restitue JAMAIS de stock : aucune écriture sur produits.quantite_stock
 * ni sur mouvements_stock. Il n'y a donc aucun risque de double déduction.
 */
class BilanLivreurController extends Controller
{
    // Quantités vendues par produit selon le SYSTÈME (livraisons livrées/validées du livreur ce jour-là)
    private function ventesSysteme(int $livreurId, string $date): array
    {
        $rows = DB::table('vente_items')
            ->join('livraisons', 'livraisons.vente_id', '=', 'vente_items.vente_id')
            ->where('livraisons.livreur_id', $livreurId)
            ->whereIn('livraisons.statut', ['livree_attente_validation', 'terminee'])
            ->whereDate('livraisons.date_livraison', $date)
            ->groupBy('vente_items.produit_id')
            ->select('vente_items.produit_id', DB::raw('SUM(vente_items.quantite) as qte'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->produit_id] = (int) $r->qte;
        }
        return $out;
    }

    private function avecVentes(BilanLivreur $bilan): array
    {
        $arr = $bilan->toArray();
        $arr['ventes_systeme'] = $this->ventesSysteme((int) $bilan->livreur_id, $bilan->date->format('Y-m-d'));
        return $arr;
    }

    // Gestionnaire enregistre les produits remis à un livreur pour la journée
    public function store(Request $request)
    {
        $roleNom = $request->user()?->role?->nom;
        if (!in_array($roleNom, ['gestionnaire', 'super_admin'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $request->validate([
            'livreur_id' => 'required|exists:users,id',
            'date'       => 'required|date',
            'lignes'     => 'required|array|min:1',
            'lignes.*.produit_id'  => 'required|exists:produits,id',
            'lignes.*.qte_remise'  => 'required|integer|min:1',
        ]);

        $existant = BilanLivreur::where('livreur_id', $request->livreur_id)
            ->whereDate('date', $request->date)
            ->first();

        if ($existant) {
            return response()->json(['message' => 'Un bilan existe déjà pour ce livreur ce jour'], 422);
        }

        $bilan = DB::transaction(function () use ($request) {
            $bilan = BilanLivreur::create([
                'livreur_id' => $request->livreur_id,
                'date'       => $request->date,
                'statut'     => 'ouvert',
            ]);

            // Regrouper si le même produit est saisi deux fois
            $cumul = [];
            foreach ($request->lignes as $ligne) {
                $cumul[$ligne['produit_id']] = ($cumul[$ligne['produit_id']] ?? 0) + (int) $ligne['qte_remise'];
            }
            foreach ($cumul as $produitId => $qte) {
                BilanLigne::create([
                    'bilan_id'   => $bilan->id,
                    'produit_id' => $produitId,
                    'qte_remise' => $qte,
                    'qte_livree' => 0,
                    'qte_retour' => 0,
                ]);
            }
            return $bilan;
        });

        return response()->json($bilan->load('lignes.produit'), 201);
    }

    // Gestionnaire : compléter un bilan encore ouvert (ajout de produits remis en cours de journée)
    public function ajouterLignes(Request $request, $id)
    {
        $roleNom = $request->user()?->role?->nom;
        if (!in_array($roleNom, ['gestionnaire', 'super_admin'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $bilan = BilanLivreur::findOrFail($id);
        if ($bilan->statut === 'cloture') {
            return response()->json(['message' => 'Bilan déjà clôturé — modification impossible'], 422);
        }

        $request->validate([
            'lignes'               => 'required|array|min:1',
            'lignes.*.produit_id'  => 'required|exists:produits,id',
            'lignes.*.qte_remise'  => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $bilan) {
            foreach ($request->lignes as $l) {
                $ligne = BilanLigne::where('bilan_id', $bilan->id)->where('produit_id', $l['produit_id'])->first();
                if ($ligne) {
                    $ligne->update(['qte_remise' => $ligne->qte_remise + (int) $l['qte_remise']]);
                } else {
                    BilanLigne::create([
                        'bilan_id'   => $bilan->id,
                        'produit_id' => $l['produit_id'],
                        'qte_remise' => (int) $l['qte_remise'],
                        'qte_livree' => 0,
                        'qte_retour' => 0,
                    ]);
                }
            }
        });

        return response()->json($bilan->load('lignes.produit'));
    }

    // Livreur consulte son bilan du jour
    public function monBilan(Request $request)
    {
        $bilan = BilanLivreur::with('lignes.produit')
            ->where('livreur_id', $request->user()->id)
            ->whereDate('date', today())
            ->first();

        if (!$bilan) {
            return response()->json(['message' => 'Aucun bilan pour aujourd\'hui'], 404);
        }

        return response()->json($this->avecVentes($bilan));
    }

    // Livreur : historique de ses bilans (reçus / livrés / restants)
    public function historique(Request $request)
    {
        $bilans = BilanLivreur::with('lignes.produit')
            ->where('livreur_id', $request->user()->id)
            ->where('date', '>=', now()->subDays(60)->toDateString())
            ->orderByDesc('date')
            ->limit(60)
            ->get();

        return response()->json($bilans);
    }

    // Livreur clôture son bilan (une seule fois, sans effet sur le stock)
    public function cloturer(Request $request, $id)
    {
        $bilan = BilanLivreur::with('lignes')->findOrFail($id);

        if ((int) $bilan->livreur_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if ($bilan->statut === 'cloture') {
            return response()->json(['message' => 'Bilan déjà clôturé'], 422);
        }

        $request->validate([
            'lignes'              => 'required|array',
            'lignes.*.id'         => 'required|exists:bilan_lignes,id',
            'lignes.*.qte_livree' => 'required|integer|min:0',
            'lignes.*.qte_retour' => 'required|integer|min:0',
            'notes'               => 'nullable|string|max:1000',
        ]);

        // Contrôle de cohérence AVANT toute écriture : livré + restant ne peut pas dépasser le remis
        $lignesBilan = $bilan->lignes->keyBy('id');
        foreach ($request->lignes as $l) {
            $ligne = $lignesBilan->get($l['id']);
            if (!$ligne) {
                return response()->json(['message' => 'Ligne invalide pour ce bilan'], 422);
            }
            if ((int) $l['qte_livree'] + (int) $l['qte_retour'] > (int) $ligne->qte_remise) {
                $nom = $ligne->produit?->nom ?? ('produit #' . $ligne->produit_id);
                return response()->json([
                    'message' => "Quantités incohérentes pour « {$nom} » : livré + restant dépasse la quantité remise ({$ligne->qte_remise})",
                ], 422);
            }
        }

        DB::transaction(function () use ($request, $bilan) {
            // Verrou pour empêcher une double clôture simultanée
            $frais = BilanLivreur::lockForUpdate()->find($bilan->id);
            if ($frais->statut === 'cloture') {
                return;
            }
            foreach ($request->lignes as $l) {
                BilanLigne::where('id', $l['id'])
                    ->where('bilan_id', $bilan->id)
                    ->update([
                        'qte_livree' => (int) $l['qte_livree'],
                        'qte_retour' => (int) $l['qte_retour'],
                    ]);
            }
            $frais->update([
                'statut'    => 'cloture',
                'valide_at' => now(),
                'notes'     => $request->notes,
            ]);
        });

        return response()->json($this->avecVentes($bilan->fresh('lignes.produit')));
    }

    // Gestionnaire / compta : bilans d'un jour donné, comparés aux ventes du système
    public function index(Request $request)
    {
        $roleNom = $request->user()?->role?->nom;
        if (!in_array($roleNom, ['gestionnaire', 'super_admin', 'compta'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $date = $request->query('date', today()->format('Y-m-d'));

        $bilans = BilanLivreur::with(['livreur', 'lignes.produit'])
            ->whereDate('date', $date)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($b) => $this->avecVentes($b));

        return response()->json($bilans->values());
    }
}
