<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // IMPORTANT : on cherche par "nom", jamais par "id".
        // Forcer un id fixe (updateOrInsert sur 'id') est dangereux : si cet id
        // était déjà occupé par un AUTRE rôle, ça renomme silencieusement ce rôle
        // et casse tous les comptes utilisateurs qui avaient ce role_id.
        $noms = ['gestionnaire', 'coordinateur', 'vendeur', 'livreur', 'super_admin'];

        foreach ($noms as $nom) {
            $existe = DB::table('roles')->where('nom', $nom)->exists();
            if (!$existe) {
                DB::table('roles')->insert([
                    'nom'        => $nom,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
