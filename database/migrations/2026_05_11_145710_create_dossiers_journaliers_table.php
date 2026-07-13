<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers_journaliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livreur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('livraison_id')->constrained('livraisons')->onDelete('cascade');
            $table->decimal('montant_carburant', 10, 2)->default(0);
            $table->enum('statut', ['ouvert', 'cloture'])->default('ouvert');
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers_journaliers');
    }
};