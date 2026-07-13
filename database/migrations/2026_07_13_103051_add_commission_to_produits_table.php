<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->decimal('commission_fixe', 10, 2)->default(0)->after('prix_gros')
                  ->comment('Commission fixe en FCFA par vente');
            $table->decimal('commission_pourcentage', 5, 2)->default(0)->after('commission_fixe')
                  ->comment('Commission en % sur le montant de la vente');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['commission_fixe', 'commission_pourcentage']);
        });
    }
};