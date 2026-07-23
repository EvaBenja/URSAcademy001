<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $boutiqueId = DB::table('boutiques')->first()->id;

        // Users
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('users')->update(['boutique_id' => $boutiqueId]);

        // Produits
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('produits')->update(['boutique_id' => $boutiqueId]);

        // Ventes
        Schema::table('ventes', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('ventes')->update(['boutique_id' => $boutiqueId]);

        // Livraisons
        Schema::table('livraisons', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('livraisons')->update(['boutique_id' => $boutiqueId]);

        // Dossiers journaliers
        Schema::table('dossiers_journaliers', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('dossiers_journaliers')->update(['boutique_id' => $boutiqueId]);

        // Depenses
        Schema::table('depenses', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('depenses')->update(['boutique_id' => $boutiqueId]);

        // Commissions
        Schema::table('commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('commissions')->update(['boutique_id' => $boutiqueId]);

        // Retraits
        Schema::table('retraits', function (Blueprint $table) {
            $table->unsignedBigInteger('boutique_id')->nullable()->after('id');
        });
        DB::table('retraits')->update(['boutique_id' => $boutiqueId]);
    }

    public function down(): void
    {
        $tables = ['users', 'produits', 'ventes', 'livraisons', 
                   'dossiers_journaliers', 'depenses', 'commissions', 'retraits'];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('boutique_id');
            });
        }
    }
};