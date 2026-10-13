<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repartitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vente_id');
            $table->unsignedBigInteger('livraison_id')->nullable();
            $table->unsignedBigInteger('produit_id')->nullable();
            $table->unsignedBigInteger('vendeur_id')->nullable();
            $table->unsignedBigInteger('livreur_id')->nullable();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->decimal('montant_vente', 12, 2)->default(0);
            $table->decimal('commission_vendeur', 10, 2)->default(0);
            $table->decimal('commission_livreur', 10, 2)->default(0);
            $table->decimal('media_buying', 10, 2)->default(0);
            $table->decimal('budget_stock', 10, 2)->default(0);
            $table->decimal('benefice', 10, 2)->default(0);
            $table->decimal('total_reparti', 10, 2)->default(0);
            $table->boolean('traitee')->default(false);
            $table->timestamp('traitee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repartitions');
    }
};