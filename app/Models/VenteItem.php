<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VenteItem extends Model {
    protected $table = 'vente_items';
    protected $fillable = ['vente_id','produit_id','quantite','prix_unitaire','prix_vendeur','remise','sous_total','couleur'];

    public function produit() {
        return $this->belongsTo(Produit::class);
    }
    public function vente() {
        return $this->belongsTo(Vente::class);
    }
}
