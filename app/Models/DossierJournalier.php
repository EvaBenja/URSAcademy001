<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DossierJournalier extends Model
{
    protected $table = 'dossiers_journaliers';

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

    // Le gestionnaire est celui qui a validé la livraison
    public function gestionnaire()
    {
        return $this->hasOneThrough(
            User::class,
            Livraison::class,
            'id',          // FK dans livraisons (livraison.id)
            'id',          // FK dans users (user.id)
            'livraison_id',// FK locale dans dossiers (dossier.livraison_id)
            'gestionnaire_id' // FK dans livraisons (livraison.gestionnaire_id)
        );
    }
}
