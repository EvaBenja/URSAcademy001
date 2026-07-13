<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ComptabiliteController extends Controller
{
    public function journalier(Request $request)
    {
        $date = $request->query('date', now()->format('Y-m-d'));

        $ventes = Vente::with(['items.produit', 'caissiere', 'livraison'])
            ->whereDate('date_vente', $date)
            ->orderBy('created_at', 'asc')
            ->get();

        $nbAnnulees = $ventes->where('statut', 'annulee')->count();
        $ventesActives = $ventes->where('statut', '!=', 'annulee');

        $caReel = 0;
        $caEnCours = 0;

        $parVendeurGroupe = $ventesActives->groupBy('caissiere_id');

        $parVendeur = $parVendeurGroupe->map(function ($ventesVendeur) {
            $vendeur = $ventesVendeur->first()->caissiere;

            $caSoumis = 0;
            $caReelVendeur = 0;
            $totalRemises = 0;

            $ventesDetail = $ventesVendeur->map(function ($vente) use (&$caSoumis, &$caReelVendeur, &$totalRemises) {
                $statutLiv = $vente->livraison->statut ?? 'sans_livraison';
                $estTerminee = $statutLiv === 'terminee';

                $montant = (float) $vente->montant_total;
                $remiseVente = (float) ($vente->items->sum('remise') ?: $vente->remise);

                $caSoumis += $montant;
                $totalRemises += $remiseVente;
                if ($estTerminee) {
                    $caReelVendeur += $montant;
                }

                $produits = $vente->items->isNotEmpty()
                    ? $vente->items->map(function ($item) {
                        return [
                            'nom'        => $item->produit->nom ?? 'Produit supprimé',
                            'couleur'    => $item->couleur,
                            'quantite'   => $item->quantite,
                            'prix'       => (float) $item->prix_vendeur,
                            'remise'     => (float) $item->remise,
                            'sous_total' => (float) $item->sous_total,
                        ];
                    })->values()
                    : collect([[
                        'nom'        => $vente->produit->nom ?? 'Produit supprimé',
                        'couleur'    => null,
                        'quantite'   => $vente->quantite,
                        'prix'       => (float) $vente->prix_vendeur,
                        'remise'     => (float) $vente->remise,
                        'sous_total' => (float) $vente->montant_total,
                    ]]);

                return [
                    'id'            => $vente->id,
                    'heure'         => $vente->created_at->format('H:i'),
                    'statut_liv'    => $statutLiv,
                    'montant'       => $montant,
                    'total_remises' => $remiseVente,
                    'produits'      => $produits,
                ];
            })->values();

            return [
                'vendeur'       => $vendeur->name ?? trim(($vendeur->prenom ?? '').' '.($vendeur->nom ?? '')) ?: 'Inconnu',
                'telephone'     => $vendeur->telephone ?? '—',
                'ca_reel'       => $caReelVendeur,
                'ca_soumis'     => $caSoumis,
                'nb_ventes'     => $ventesDetail->count(),
                'total_remises' => $totalRemises,
                'ventes'        => $ventesDetail,
            ];
        })->values();

        $caReel = $parVendeur->sum('ca_reel');
        $caEnCours = $parVendeur->sum(fn($v) => $v['ca_soumis'] - $v['ca_reel']);

        return response()->json([
            'ca_reel'     => $caReel,
            'ca_en_cours' => $caEnCours,
            'nb_ventes'   => $ventesActives->count(),
            'nb_annulees' => $nbAnnulees,
            'par_vendeur' => $parVendeur,
        ]);
    }
}