<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'prenom', 'nom', 'email', 'password',
        'role_id', 'telephone', 'statut',
        'latitude', 'longitude', 'position_updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'email_verified_at',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function estGestionnaire()
    {
        return $this->role->nom === 'gestionnaire';
    }

    public function estLivreur()
    {
        return $this->role->nom === 'livreur';
    }

    public function estVendeur()
    {
        return $this->role->nom === 'vendeur';
    }
}