<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repartition extends Model
{
    protected $fillable = [
        'vente_id', 'livraison_id', 'produit_id',
        'vendeur_id', 'livreur_id', 'boutique_id',
        'montant_vente', 'commission_vendeur', 'commission_livreur',
        'media_buying', 'budget_stock', 'benefice',
        'total_reparti', 'traitee', 'traitee_le',
    ];

    protected $casts = [
        'traitee'    => 'boolean',
        'traitee_le' => 'datetime',
    ];

    public function vente()    { return $this->belongsTo(Vente::class); }
    public function vendeur()  { return $this->belongsTo(User::class, 'vendeur_id'); }
    public function livreur()  { return $this->belongsTo(User::class, 'livreur_id'); }
    public function produit()  { return $this->belongsTo(Produit::class); }
}