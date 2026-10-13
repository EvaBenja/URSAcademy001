<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Livraison;

class PurgerLivraisonsRejetees extends Command
{
    protected $signature   = 'livraisons:purger-rejetees';
    protected $description = 'Supprime les livraisons rejetées depuis plus de 3 jours';

    public function handle()
    {
        $nb = Livraison::where('statut', 'rejetee')
            ->where('updated_at', '<=', now()->subDays(3))
            ->count();

        Livraison::where('statut', 'rejetee')
            ->where('updated_at', '<=', now()->subDays(3))
            ->delete();

        $this->info("{$nb} livraison(s) rejetée(s) supprimée(s).");
        return 0;
    }
}
