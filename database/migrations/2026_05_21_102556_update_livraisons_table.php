<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->string('zone_livraison')->nullable()->after('notes');
            $table->text('motif_rejet')->nullable()->after('zone_livraison');
            $table->enum('statut', [
                'en_attente', 'validee', 'en_cours',
                'rejetee', 'terminee'
            ])->default('en_attente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropColumn(['zone_livraison', 'motif_rejet']);
        });
    }
};