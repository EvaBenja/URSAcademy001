<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            if (!Schema::hasColumn('livraisons', 'client_latitude')) {
                $table->decimal('client_latitude', 10, 7)->nullable()->after('client_quartier');
            }
            if (!Schema::hasColumn('livraisons', 'client_longitude')) {
                $table->decimal('client_longitude', 10, 7)->nullable()->after('client_latitude');
            }
        });
    }
    public function down(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropColumn(['client_latitude', 'client_longitude']);
        });
    }
};
