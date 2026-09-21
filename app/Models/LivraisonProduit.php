<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LivraisonProduit extends Model {
    protected $table = 'livraison_produits';
    protected $fillable = ['livraison_id', 'produit_id', 'quantite', 'statut'];

    public function produit() {
        return $this->belongsTo(Produit::class);
    }
    public function livraison() {
        return $this->belongsTo(Livraison::class);
    }
}
