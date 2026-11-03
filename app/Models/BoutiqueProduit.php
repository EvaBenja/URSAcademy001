<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoutiqueProduit extends Model
{
    protected $table = 'boutique_produits';

    protected $fillable = [
        'vendeur_id', 'produit_id', 'titre', 'description',
        'images', 'video', 'actif', 'ordre',
    ];

    protected $casts = [
        'images' => 'array',
        'actif'  => 'boolean',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function vendeur()
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }
}
