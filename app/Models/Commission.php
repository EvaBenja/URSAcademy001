<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'user_id','vente_id','produit_id',
        'montant_vente','commission_fixe','commission_pourcentage','montant_commission',
        'statut','montant_initial','motif_modification','modifie_par','modifie_le',
    ];

    protected $casts = [
        'montant_vente'          => 'decimal:2',
        'commission_fixe'        => 'decimal:2',
        'commission_pourcentage' => 'decimal:2',
        'montant_commission'     => 'decimal:2',
        'montant_initial'        => 'decimal:2',
        'modifie_le'             => 'datetime',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function vente()     { return $this->belongsTo(Vente::class); }
    public function produit()   { return $this->belongsTo(Produit::class); }
    public function modifiePar(){ return $this->belongsTo(User::class, 'modifie_par'); }
}
