<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retraits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('montant', 12, 2);
            $table->string('numero_orange')->comment('Numéro Orange Money du vendeur');
            $table->enum('statut', ['en_attente', 'approuve', 'refuse', 'paye'])
                  ->default('en_attente');
            $table->text('notes')->nullable();
            $table->string('motif_refus')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retraits');
    }
};