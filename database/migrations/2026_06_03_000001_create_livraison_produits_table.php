<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('livraison_produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livraison_id')->constrained('livraisons')->onDelete('cascade');
            $table->foreignId('produit_id')->constrained('produits')->onDelete('cascade');
            $table->integer('quantite')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('livraison_produits');
    }
};
