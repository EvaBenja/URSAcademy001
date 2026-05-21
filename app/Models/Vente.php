<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vente extends Model
{
    protected $fillable = [
        'produit_id', 'caissiere_id', 'quantite',
        'prix_unitaire', 'prix_vendeur', 'remise',
        'montant_total', 'date_vente', 'zone_livraison',
        'statut', 'notes'
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function caissiere()
    {
        return $this->belongsTo(User::class, 'caissiere_id');
    }
}