<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DossierJournalier extends Model
{
    protected $table = 'dossiers_journaliers'; // ← ligne ajoutée

    protected $fillable = [
        'livreur_id', 'livraison_id',
        'montant_carburant', 'statut', 'date'
    ];

    public function livreur()
    {
        return $this->belongsTo(User::class, 'livreur_id');
    }

    public function livraison()
    {
        return $this->belongsTo(Livraison::class);
    }
}