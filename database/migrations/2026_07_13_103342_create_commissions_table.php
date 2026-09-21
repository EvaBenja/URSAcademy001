<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')
                  ->comment('Le vendeur');
            $table->foreignId('vente_id')->constrained()->onDelete('cascade');
            $table->foreignId('produit_id')->constrained()->onDelete('cascade');
            $table->decimal('montant_vente', 12, 2);
            $table->decimal('commission_fixe', 10, 2)->default(0);
            $table->decimal('commission_pourcentage', 5, 2)->default(0);
            $table->decimal('montant_commission', 10, 2);
            $table->enum('statut', ['en_attente', 'validee', 'payee'])->default('en_attente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};