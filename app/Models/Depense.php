<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Depense extends Model {
    protected $fillable = ['user_id','categorie','motif','montant','date_depense','notes'];
    public function user() { return $this->belongsTo(User::class); }
}
