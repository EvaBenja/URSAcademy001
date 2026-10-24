<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bilans_livreurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livreur_id')->constrained('users');
            $table->date('date');
            $table->enum('statut', ['ouvert', 'cloture'])->default('ouvert');
            $table->timestamp('valide_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bilan_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bilan_id')->constrained('bilans_livreurs')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits');
            $table->integer('qte_remise')->default(0);
            $table->integer('qte_livree')->default(0);
            $table->integer('qte_retour')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bilan_lignes');
        Schema::dropIfExists('bilans_livreurs');
    }
};