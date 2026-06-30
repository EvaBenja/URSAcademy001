<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            if (!Schema::hasColumn('livraisons', 'photo_recu')) {
                $table->string('photo_recu')->nullable()->after('notes');
            }
        });
    }
    public function down(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropColumn('photo_recu');
        });
    }
};
