<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            // Commission livreur
            $table->decimal('commission_livreur_fixe', 10, 2)->default(0)->after('commission_pourcentage')
                  ->comment('Commission fixe livreur en FCFA');
            $table->decimal('commission_livreur_pourcentage', 5, 2)->default(0)->after('commission_livreur_fixe')
                  ->comment('Commission livreur en % sur le montant');

            // Budget Media Buying
            $table->decimal('budget_media_buying', 10, 2)->default(0)->after('commission_livreur_pourcentage')
                  ->comment('Montant réservé au budget pub par vente');

            // Budget Stock
            $table->decimal('budget_stock', 10, 2)->default(0)->after('budget_media_buying')
                  ->comment('Montant réservé au réapprovisionnement stock');

            // Bénéfice
            $table->decimal('benefice', 10, 2)->default(0)->after('budget_stock')
                  ->comment('Bénéfice net par vente');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn([
                'commission_livreur_fixe',
                'commission_livreur_pourcentage',
                'budget_media_buying',
                'budget_stock',
                'benefice',
            ]);
        });
    }
};