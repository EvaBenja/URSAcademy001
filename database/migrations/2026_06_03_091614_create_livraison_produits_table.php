<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration de sécurité — la table existe déjà, on skip silencieusement
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('livraison_produits')) {
            Schema::create('livraison_produits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('livraison_id')->constrained('livraisons')->onDelete('cascade');
                $table->foreignId('produit_id')->constrained('produits')->onDelete('cascade');
                $table->integer('quantite')->default(1);
                $table->timestamps();
            });
        }
    }
    public function down(): void {
        // Ne pas supprimer — gérée par une autre migration
    }
};
