<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BilanLigne extends Model
{
    protected $table = 'bilan_lignes';

    protected $fillable = [
        'bilan_id', 'produit_id', 'qte_remise', 'qte_livree', 'qte_retour', 'note'
    ];

    public function produit() { return $this->belongsTo(Produit::class); }
    public function bilan()   { return $this->belongsTo(BilanLivreur::class, 'bilan_id'); }
}