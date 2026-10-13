<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('benefices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->unsignedBigInteger('vente_id')->nullable();
            $table->unsignedBigInteger('livraison_id')->nullable();
            $table->decimal('montant_vente', 12, 2)->default(0);
            $table->decimal('commission_vendeur', 10, 2)->default(0);
            $table->decimal('commission_livreur', 10, 2)->default(0);
            $table->decimal('media_buying', 10, 2)->default(0);
            $table->decimal('budget_stock', 10, 2)->default(0);
            $table->decimal('benefice_net', 10, 2)->default(0)
                  ->comment('Bénéfice réel après toutes déductions');
            $table->date('date_vente')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefices');
    }
};