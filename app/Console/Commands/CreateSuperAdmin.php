<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=Super Admin} {--password=password123}';
    protected $description = 'Crée ou promeut un utilisateur en super_admin';

    public function handle()
    {
        $email = $this->argument('email');

        // S'assurer que le rôle super_admin existe
        $role = Role::firstOrCreate(['nom' => 'super_admin']);

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['role_id' => $role->id]);
            $this->info("✓ Utilisateur existant '{$email}' promu en super_admin (id={$user->id})");
        } else {
            $name = $this->option('name');
            $parts = explode(' ', $name, 2);
            $user = User::create([
                'name'     => $name,
                'prenom'   => $parts[0] ?? $name,
                'nom'      => $parts[1] ?? '',
                'email'    => $email,
                'password' => Hash::make($this->option('password')),
                'role_id'  => $role->id,
                'statut'   => 'actif',
            ]);
            $this->info("✓ Nouvel utilisateur super_admin créé : {$email} (id={$user->id})");
            $this->warn("  Mot de passe : {$this->option('password')}");
        }

        return 0;
    }
}
