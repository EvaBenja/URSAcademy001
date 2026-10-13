<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration commandes/flux — crée seulement si pas encore existantes
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('commandes')) {
            Schema::create('commandes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vente_id')->nullable()->constrained('ventes')->onDelete('set null');
                $table->foreignId('livreur_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('statut')->default('en_attente');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('flux')) {
            Schema::create('flux', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->decimal('montant', 10, 2)->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('flux');
        Schema::dropIfExists('commandes');
    }
};
