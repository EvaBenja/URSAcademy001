<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table NOUVELLE : aucune donnée existante n'est modifiée.
        if (Schema::hasTable('boutique_produits')) {
            return;
        }
        Schema::create('boutique_produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendeur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits')->onDelete('cascade');
            $table->string('titre', 150);
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('video')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['vendeur_id', 'produit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boutique_produits');
    }
};
