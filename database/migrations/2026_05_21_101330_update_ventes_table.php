<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->decimal('prix_vendeur', 10, 2)->nullable()->after('prix_unitaire'); // prix libre du vendeur
            $table->decimal('remise', 10, 2)->default(0)->after('prix_vendeur'); // remise accordée
            $table->string('zone_livraison')->nullable()->after('remise'); // zone de livraison
            $table->enum('statut', ['en_attente', 'validee', 'annulee'])->default('en_attente')->after('zone_livraison');
            $table->text('notes')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['prix_vendeur', 'remise', 'zone_livraison', 'statut', 'notes']);
        });
    }
};