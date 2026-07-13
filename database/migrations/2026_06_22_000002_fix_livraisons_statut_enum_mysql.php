<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // MySQL : modifier l'ENUM pour inclure tous les statuts utilisés dans l'app.
        // SQLite ignore les ENUMs (stocke en TEXT), donc cette migration n'y a aucun effet.
        DB::statement("
            ALTER TABLE livraisons
            MODIFY COLUMN statut ENUM(
                'en_attente',
                'validee',
                'en_cours',
                'rejetee',
                'livree_attente_validation',
                'terminee'
            ) NOT NULL DEFAULT 'en_attente'
        ");
    }

    public function down(): void {
        DB::statement("
            ALTER TABLE livraisons
            MODIFY COLUMN statut ENUM(
                'en_attente',
                'validee',
                'en_cours',
                'rejetee',
                'terminee'
            ) NOT NULL DEFAULT 'en_attente'
        ");
    }
};
