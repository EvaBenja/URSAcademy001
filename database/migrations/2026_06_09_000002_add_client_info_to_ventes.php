<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'client_nom')) {
                $table->string('client_nom')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('ventes', 'client_telephone')) {
                $table->string('client_telephone')->nullable()->after('client_nom');
            }
            if (!Schema::hasColumn('ventes', 'client_quartier')) {
                $table->string('client_quartier')->nullable()->after('client_telephone');
            }
        });
    }
    public function down(): void {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['client_nom','client_telephone','client_quartier']);
        });
    }
};
