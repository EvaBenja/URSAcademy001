<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livreur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('gestionnaire_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('statut', ['en_attente', 'validee', 'en_cours', 'terminee'])->default('en_attente');
            $table->text('notes')->nullable();
            $table->date('date_livraison');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livraisons');
    }
};