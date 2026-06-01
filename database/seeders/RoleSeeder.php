<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // On insère les rôles dans l'ordre pour avoir les IDs 1, 2, 3, 4
        DB::table('roles')->insert([
            ['id' => 1, 'nom' => 'gestionnaire', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nom' => 'coordinateur', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nom' => 'vendeur',      'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'nom' => 'livreur',      'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
