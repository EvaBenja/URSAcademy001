<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BilanLivreur extends Model
{
    protected $table = 'bilans_livreurs';

    protected $fillable = [
        'livreur_id', 'date', 'statut', 'valide_at', 'notes'
    ];

    protected $casts = [
        'valide_at' => 'datetime',
        'date'      => 'date',
    ];

    public function livreur() { return $this->belongsTo(User::class, 'livreur_id'); }
    public function lignes()  { return $this->hasMany(BilanLigne::class, 'bilan_id'); }
}