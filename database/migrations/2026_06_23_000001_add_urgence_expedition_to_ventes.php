<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'note_urgence')) {
                $table->string('note_urgence')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('ventes', 'est_expedition')) {
                $table->boolean('est_expedition')->default(false)->after('note_urgence');
            }
        });
    }
    public function down(): void {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['note_urgence', 'est_expedition']);
        });
    }
};
