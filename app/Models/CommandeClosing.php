<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandeClosing extends Model
{
    protected $table = 'commandes_closing';

    protected $fillable = [
        'boutique_id',
        'shopify_order_id',
        'shopify_order_number',
        'client_nom',
        'client_telephone',
        'client_email',
        'client_adresse',
        'client_ville',
        'client_pays',
        'montant_total',
        'devise',
        'produits',
        'statut',
        'motif_rejet',
        'motif_relance',
        'vendeur_id',
        'vente_id',
        'prise_le',
        'traitee_le',
        'shopify_data',
    ];

    protected $casts = [
        'produits'     => 'array',
        'shopify_data' => 'array',
        'montant_total'=> 'decimal:2',
        'prise_le'     => 'datetime',
        'traitee_le'   => 'datetime',
    ];

    public function vendeur()
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function vente()
    {
        return $this->belongsTo(Vente::class);
    }

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }
}