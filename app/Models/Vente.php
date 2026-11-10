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

    /** Vente issue du closing Shopify : le vrai nom des produits est dans les notes. */
    public function estShopify(): bool
    {
        return is_string($this->notes) && str_starts_with($this->notes, 'Shopify #');
    }

    /**
     * Nom du produit à afficher : le(s) vrai(s) produit(s) Shopify pour une commande Shopify,
     * sinon le nom du produit sans le préfixe [Archivé]. N'écrit rien en base.
     */
    public function getNomProduitAfficheAttribute(): ?string
    {
        if ($this->estShopify()) {
            $apres = strstr($this->notes, '|');
            $txt = $apres !== false ? trim(substr($apres, 1)) : '';
            if ($txt !== '') {
                return $txt;
            }
        }
        $nom = $this->produit?->nom;
        return $nom ? trim(preg_replace('/(\[Arch[ié]v[eé]\]\s*)*/iu', '', $nom)) : null;
    }

    /** Toute réponse JSON contenant une vente affiche le bon nom (liste, livraison, compta…). */
    public function toArray()
    {
        $arr = parent::toArray();
        if ($this->relationLoaded('produit') && isset($arr['produit']) && is_array($arr['produit'])) {
            $nom = $this->nom_produit_affiche;
            if ($nom) {
                $arr['produit']['nom'] = $nom;
            }
        }
        return $arr;
    }

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
