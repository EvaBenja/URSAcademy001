<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Benefice extends Model
{
    protected $fillable = [
        'boutique_id', 'vente_id', 'livraison_id',
        'montant_vente', 'commission_vendeur', 'commission_livreur',
        'media_buying', 'budget_stock', 'benefice_net', 'date_vente',
    ];

    protected $casts = [
        'date_vente' => 'date',
    ];

    public function vente()    { return $this->belongsTo(Vente::class); }
    public function livraison(){ return $this->belongsTo(Livraison::class); }
}