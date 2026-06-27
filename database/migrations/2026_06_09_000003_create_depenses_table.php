<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('depenses')) {
            Schema::create('depenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('categorie'); // courant, produits, loyer, salaire, autre
                $table->string('motif');
                $table->decimal('montant', 10, 2);
                $table->date('date_depense');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('depenses');
    }
};
