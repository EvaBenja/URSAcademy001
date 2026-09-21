<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('produits', function (Blueprint $table) {
            if (!Schema::hasColumn('produits', 'prix_gros')) {
                $table->decimal('prix_gros', 10, 2)->nullable()->after('prix_unitaire');
            }
        });
    }
    public function down(): void {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('prix_gros');
        });
    }
};
