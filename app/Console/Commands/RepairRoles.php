<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class RepairRoles extends Command
{
    protected $signature = 'roles:repair';
    protected $description = 'Diagnostique et corrige les comptes utilisateurs dont le rôle ne correspond pas à leur email connu';

    // Mapping email → rôle attendu, basé sur les comptes connus du projet.
    // Si un email n'est pas dans cette liste, son rôle actuel est laissé tel quel.
    private array $emailRoleMap = [
        'admin@urs.com'         => 'super_admin',
        'gestionnaire2@urs.com' => 'gestionnaire',
        'gestionnaire@urs.com'  => 'vendeur', // historique : ce compte est en fait un vendeur
        'cordonnateur@urs.com'  => 'coordinateur',
        'coordinateur@urs.com'  => 'coordinateur',
        'vendeur@urs.com'       => 'vendeur',
        'livreur@urs.com'       => 'livreur',
    ];

    public function handle()
    {
        $this->info('=== État actuel des rôles ===');
        $roles = Role::all();
        foreach ($roles as $r) {
            $this->line("  id={$r->id}  nom={$r->nom}");
        }

        $this->info('');
        $this->info('=== État actuel des utilisateurs ===');
        $users = User::with('role')->get();
        foreach ($users as $u) {
            $this->line("  id={$u->id}  email={$u->email}  role_id={$u->role_id}  role=" . ($u->role->nom ?? '⚠️ AUCUN'));
        }

        $this->info('');
        $this->info('=== Correction des comptes connus ===');
        $corrected = 0;
        foreach ($this->emailRoleMap as $email => $roleNom) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                continue;
            }
            $role = Role::where('nom', $roleNom)->first();
            if (!$role) {
                $this->warn("  Rôle '{$roleNom}' introuvable en base, impossible de corriger {$email}");
                continue;
            }
            if ($user->role_id !== $role->id) {
                $oldRole = $user->role->nom ?? 'aucun';
                $user->update(['role_id' => $role->id]);
                $this->info("  ✓ {$email} : {$oldRole} → {$roleNom}");
                $corrected++;
            } else {
                $this->line("  ✓ {$email} déjà correct ({$roleNom})");
            }
        }

        $this->info('');
        $this->info("Terminé. {$corrected} compte(s) corrigé(s).");
        return 0;
    }
}
