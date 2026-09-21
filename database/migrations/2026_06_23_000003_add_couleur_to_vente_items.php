<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('vente_items', function (Blueprint $table) {
            if (!Schema::hasColumn('vente_items', 'couleur')) {
                $table->string('couleur')->nullable()->after('sous_total');
            }
        });
    }
    public function down(): void {
        Schema::table('vente_items', function (Blueprint $table) {
            $table->dropColumn('couleur');
        });
    }
};
