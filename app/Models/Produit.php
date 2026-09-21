<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    protected $fillable = [
        'nom',
        'reference',
        'prix_unitaire',
        'prix_gros',
        'quantite_stock',
        'unite',
        'boutique_id',
        // Commissions vendeur
        'commission_fixe',
        'commission_pourcentage',
        // Commissions livreur
        'commission_livreur_fixe',
        'commission_livreur_pourcentage',
        // Budget media buying
        'budget_media_buying',
        // Budget stock
        'budget_stock',
        // Bénéfice
        'benefice',
    ];

    protected $casts = [
        'prix_unitaire'                  => 'decimal:2',
        'prix_gros'                      => 'decimal:2',
        'commission_fixe'                => 'decimal:2',
        'commission_pourcentage'         => 'decimal:2',
        'commission_livreur_fixe'        => 'decimal:2',
        'commission_livreur_pourcentage' => 'decimal:2',
        'budget_media_buying'            => 'decimal:2',
        'budget_stock'                   => 'decimal:2',
        'benefice'                       => 'decimal:2',
    ];

    public function ventes()
    {
        return $this->hasMany(Vente::class);
    }

    public function mouvements()
    {
        return $this->hasMany(MouvementStock::class);
    }

    // Calculer la répartition complète pour une vente donnée
    public function repartition(float $montantVente): array
    {
        $commVendeur  = $this->commission_fixe + ($montantVente * $this->commission_pourcentage / 100);
        $commLivreur  = $this->commission_livreur_fixe + ($montantVente * $this->commission_livreur_pourcentage / 100);
        $mediabuying  = (float) $this->budget_media_buying;
        $stock        = (float) $this->budget_stock;
        $benefice     = (float) $this->benefice;

        return [
            'montant_vente'      => $montantVente,
            'commission_vendeur' => round($commVendeur, 2),
            'commission_livreur' => round($commLivreur, 2),
            'media_buying'       => round($mediabuying, 2),
            'budget_stock'       => round($stock, 2),
            'benefice'           => round($benefice, 2),
            'total_reparti'      => round($commVendeur + $commLivreur + $mediabuying + $stock + $benefice, 2),
        ];
    }
}