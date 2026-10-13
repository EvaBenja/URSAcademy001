<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Vente extends Model {
    protected $fillable = [
        'produit_id','caissiere_id','quantite',
        'prix_unitaire','prix_vendeur','remise',
        'montant_total','date_vente','zone_livraison',
        'statut','notes','note_urgence','est_expedition',
        'client_nom','client_telephone','client_quartier','motif_annulation',
        'vendeur_latitude','vendeur_longitude'
    ];

    public function produit()   { return $this->belongsTo(Produit::class); }
    public function caissiere() { return $this->belongsTo(User::class, 'caissiere_id'); }
    public function livraison() { return $this->hasOne(Livraison::class); }
    public function items() {
        if (Schema::hasTable('vente_items')) {
            return $this->hasMany(VenteItem::class)->with('produit');
        }
        return $this->hasMany(VenteItem::class)->whereRaw('1=0');
    }
}
