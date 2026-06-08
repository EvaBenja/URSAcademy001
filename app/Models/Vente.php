<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Vente extends Model {
    protected $fillable = [
        'produit_id','caissiere_id','quantite',
        'prix_unitaire','prix_vendeur','remise',
        'montant_total','date_vente','zone_livraison',
        'statut','notes'
    ];

    public function produit()   { return $this->belongsTo(Produit::class); }
    public function caissiere() { return $this->belongsTo(User::class, 'caissiere_id'); }

    public function items() {
        // Ne charge la relation que si la table existe
        if (Schema::hasTable('vente_items')) {
            return $this->hasMany(VenteItem::class)->with('produit');
        }
        // Retourne une relation vide
        return $this->hasMany(VenteItem::class)->whereRaw('1=0');
    }
}
