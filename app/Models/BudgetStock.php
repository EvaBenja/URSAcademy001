<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetStock extends Model
{
    protected $table = 'budget_stock';

    protected $fillable = [
        'boutique_id', 'vente_id', 'traite_par',
        'type', 'montant', 'description', 'solde_apres',
    ];

    public function traitePar() { return $this->belongsTo(User::class, 'traite_par'); }
    public function vente()     { return $this->belongsTo(Vente::class); }
}