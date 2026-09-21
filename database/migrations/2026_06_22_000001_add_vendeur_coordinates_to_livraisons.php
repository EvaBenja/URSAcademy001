<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            if (!Schema::hasColumn('livraisons', 'vendeur_latitude')) {
                $table->decimal('vendeur_latitude', 10, 7)->nullable()->after('client_longitude');
            }
            if (!Schema::hasColumn('livraisons', 'vendeur_longitude')) {
                $table->decimal('vendeur_longitude', 10, 7)->nullable()->after('vendeur_latitude');
            }
        });
    }
    public function down(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropColumn(['vendeur_latitude', 'vendeur_longitude']);
        });
    }
};
