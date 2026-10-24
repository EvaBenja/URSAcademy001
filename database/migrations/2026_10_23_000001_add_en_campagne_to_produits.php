<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->boolean('en_campagne')->default(false)->after('quantite_stock');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('en_campagne');
        });
    }
};