<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('livraison_produits') && !Schema::hasColumn('livraison_produits', 'statut')) {
            Schema::table('livraison_produits', function (Blueprint $table) {
                $table->enum('statut', ['en_attente', 'livre', 'non_livre'])->default('en_attente')->after('quantite');
            });
        }
    }
    public function down(): void {
        if (Schema::hasColumn('livraison_produits', 'statut')) {
            Schema::table('livraison_produits', function (Blueprint $table) {
                $table->dropColumn('statut');
            });
        }
    }
};
