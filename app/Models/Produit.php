<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    protected $fillable = [
        'nom', 'reference', 'prix_unitaire', 'quantite_stock', 'unite'
    ];

    public function ventes()
    {
        return $this->hasMany(Vente::class);
    }

    public function mouvements()
    {
        return $this->hasMany(MouvementStock::class);
    }
}