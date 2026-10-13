<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['nom' => 'super_admin']);

        User::firstOrCreate(
            ['email' => 'admin@urs.com'],
            [
                'name'     => 'Eva Admin',
                'prenom'   => 'Eva',
                'nom'      => 'Admin',
                'password' => Hash::make('admin2026'),
                'role_id'  => $role->id,
                'statut'   => 'actif',
            ]
        );
    }
}
