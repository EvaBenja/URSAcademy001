<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_buying', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->unsignedBigInteger('vendeur_id')->nullable()
                  ->comment('Vendeur auquel ce budget est lié');
            $table->unsignedBigInteger('vente_id')->nullable();
            $table->unsignedBigInteger('traite_par')->nullable()
                  ->comment('Media buyer ou super admin qui a fait l\'opération');
            $table->enum('type', ['credit', 'debit'])
                  ->comment('credit=entrée automatique, debit=dépense campagne');
            $table->decimal('montant', 12, 2);
            $table->string('description')->nullable();
            $table->decimal('solde_apres', 12, 2)->default(0)
                  ->comment('Solde du compte media buying après opération');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_buying');
    }
};