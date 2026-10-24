<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vente_items', function (Blueprint $table) {
            $table->boolean('stock_regularise')->default(false)->after('sous_total');
        });
    }

    public function down(): void
    {
        Schema::table('vente_items', function (Blueprint $table) {
            $table->dropColumn('stock_regularise');
        });
    }
};