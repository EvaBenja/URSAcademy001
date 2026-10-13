<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code_boutique')->unique()->nullable()->after('boutique_id')
                  ->comment('Code unique pour la boutique personnalisée du vendeur');
            $table->string('nom_boutique')->nullable()->after('code_boutique')
                  ->comment('Nom de la boutique personnalisée');
            $table->text('description_boutique')->nullable()->after('nom_boutique');
        });

        // Générer un code boutique pour les vendeurs existants
        $vendeurs = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.nom', 'vendeur')
            ->select('users.id', 'users.name')
            ->get();

        foreach ($vendeurs as $vendeur) {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $vendeur->name), 0, 6))
                  . '-' . strtoupper(substr(md5($vendeur->id . time()), 0, 4));
            DB::table('users')->where('id', $vendeur->id)->update(['code_boutique' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['code_boutique', 'nom_boutique', 'description_boutique']);
        });
    }
};