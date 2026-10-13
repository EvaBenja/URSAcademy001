<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaBuying extends Model
{
    protected $table = 'media_buying';

    protected $fillable = [
        'boutique_id', 'vendeur_id', 'vente_id',
        'traite_par', 'type', 'montant',
        'description', 'solde_apres',
    ];

    public function vendeur()   { return $this->belongsTo(User::class, 'vendeur_id'); }
    public function traitePar() { return $this->belongsTo(User::class, 'traite_par'); }
    public function vente()     { return $this->belongsTo(Vente::class); }
}