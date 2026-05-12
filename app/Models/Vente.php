<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vente extends Model
{
    protected $fillable = [
        'produit_id', 'caissiere_id', 'quantite',
        'prix_unitaire', 'montant_total', 'date_vente'
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