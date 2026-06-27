<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            if (!Schema::hasColumn('livraisons', 'notif_livreur_lu')) {
                $table->boolean('notif_livreur_lu')->default(false)->after('statut');
            }
        });
    }
    public function down(): void {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropColumn('notif_livreur_lu');
        });
    }
};
