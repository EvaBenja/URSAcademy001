<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            if (!Schema::hasColumn('livraisons', 'vente_id')) {
                $table->foreignId('vente_id')->nullable()->after('id')
                    ->constrained('ventes')->onDelete('set null');
            }
            if (!Schema::hasColumn('livraisons', 'client_nom')) {
                $table->string('client_nom')->nullable()->after('zone_livraison');
            }
            if (!Schema::hasColumn('livraisons', 'client_telephone')) {
                $table->string('client_telephone')->nullable()->after('client_nom');
            }
            if (!Schema::hasColumn('livraisons', 'client_quartier')) {
                $table->string('client_quartier')->nullable()->after('client_telephone');
            }
        });
    }
    public function down(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropForeign(['vente_id']);
            $table->dropColumn(['vente_id','client_nom','client_telephone','client_quartier']);
        });
    }
};
