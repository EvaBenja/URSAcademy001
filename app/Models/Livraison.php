<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Livraison extends Model {
    protected $fillable = [
        'livreur_id','gestionnaire_id','statut','notes',
        'date_livraison','zone_livraison','motif_rejet'
    ];

    public function livreur()      { return $this->belongsTo(User::class, 'livreur_id'); }
    public function gestionnaire() { return $this->belongsTo(User::class, 'gestionnaire_id'); }
    public function dossier()      { return $this->hasOne(DossierJournalier::class); }
    public function produits()     { return $this->hasMany(LivraisonProduit::class)->with('produit'); }
}
