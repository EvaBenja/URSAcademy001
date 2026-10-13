<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'montant_initial'))
                $table->decimal('montant_initial', 10, 2)->nullable()->after('montant_commission');
            if (!Schema::hasColumn('commissions', 'motif_modification'))
                $table->text('motif_modification')->nullable()->after('montant_initial');
            if (!Schema::hasColumn('commissions', 'modifie_par'))
                $table->unsignedBigInteger('modifie_par')->nullable()->after('motif_modification');
            if (!Schema::hasColumn('commissions', 'modifie_le'))
                $table->timestamp('modifie_le')->nullable()->after('modifie_par');
        });
    }
    public function down(): void {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn(['montant_initial','motif_modification','modifie_par','modifie_le']);
        });
    }
};
