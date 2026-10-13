<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->string('valeur');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insérer la valeur par défaut du prix de livraison
        DB::table('configurations')->insert([
            'cle'         => 'prix_livraison',
            'valeur'      => '1000',
            'description' => 'Prix fixe de livraison en FCFA',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};