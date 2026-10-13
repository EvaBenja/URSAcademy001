<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purger les livraisons rejetées depuis plus de 3 jours — chaque nuit à 2h
Schedule::command('livraisons:purger-rejetees')->dailyAt('02:00');
