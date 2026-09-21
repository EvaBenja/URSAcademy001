<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vente_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits')->onDelete('cascade');
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('prix_vendeur', 10, 2)->nullable();
            $table->decimal('remise', 10, 2)->default(0);
            $table->decimal('sous_total', 10, 2);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('vente_items');
    }
};
