<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Boutique extends Model
{
    protected $fillable = [
        'nom',
        'pays',
        'ville',
        'adresse',
        'telephone',
        'email',
        'logo',
        'code_invitation',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    // Générer automatiquement un code d'invitation unique
    public static function genererCode(string $nom): string
    {
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nom), 0, 8));
        $code = $base . '-' . strtoupper(Str::random(6));
        while (self::where('code_invitation', $code)->exists()) {
            $code = $base . '-' . strtoupper(Str::random(6));
        }
        return $code;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function produits()
    {
        return $this->hasMany(Produit::class);
    }

    public function ventes()
    {
        return $this->hasMany(Vente::class);
    }

    public function livraisons()
    {
        return $this->hasMany(Livraison::class);
    }

    public function depenses()
    {
        return $this->hasMany(Depense::class);
    }
}