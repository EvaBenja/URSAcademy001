<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Retrait extends Model
{
    protected $fillable = [
        'user_id',
        'montant',
        'numero_orange',
        'statut',
        'notes',
        'motif_refus',
        'traite_par',
        'traite_le',
    ];

    protected $casts = [
        'montant'   => 'decimal:2',
        'traite_le' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}