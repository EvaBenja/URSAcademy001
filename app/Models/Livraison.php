<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Livraison extends Model {
    protected $fillable = [
        'vente_id','livreur_id','gestionnaire_id',
        'statut','notes','date_livraison','zone_livraison','motif_rejet','motif_rejet_categorie',
        'notif_livreur_lu',
        'client_nom','client_telephone','client_quartier','client_latitude','client_longitude',
        'vendeur_latitude','vendeur_longitude'
    ];

    public function vente()        { return $this->belongsTo(Vente::class); }
    public function livreur()      { return $this->belongsTo(User::class, 'livreur_id'); }
    public function gestionnaire() { return $this->belongsTo(User::class, 'gestionnaire_id'); }
    public function dossier()      { return $this->hasOne(DossierJournalier::class); }
    public function produits()     {
        return \Illuminate\Support\Facades\Schema::hasTable('livraison_produits')
            ? $this->hasMany(LivraisonProduit::class)->with('produit')
            : $this->hasMany(LivraisonProduit::class)->whereRaw('1=0');
    }
}
