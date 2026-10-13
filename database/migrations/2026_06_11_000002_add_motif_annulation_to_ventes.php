<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'motif_annulation')) {
                $table->string('motif_annulation')->nullable()->after('statut');
            }
        });
    }
    public function down(): void {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn('motif_annulation');
        });
    }
};
