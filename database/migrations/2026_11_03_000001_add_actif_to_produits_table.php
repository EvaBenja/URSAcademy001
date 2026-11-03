<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sécurité : si la colonne existe déjà, on ne fait rien
        if (Schema::hasColumn('produits', 'actif')) {
            return;
        }

        Schema::table('produits', function (Blueprint $table) {
            // true par défaut : tous les produits existants restent actifs
            $table->boolean('actif')->default(true)->after('en_campagne');
        });

        // Les produits déjà archivés (nom commençant par [Archivé]) deviennent inactifs
        DB::table('produits')
            ->where('nom', 'like', '[Archivé]%')
            ->update(['actif' => false]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('produits', 'actif')) {
            Schema::table('produits', function (Blueprint $table) {
                $table->dropColumn('actif');
            });
        }
    }
};