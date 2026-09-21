<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('pays')->default('Burkina Faso');
            $table->string('ville')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->string('code_invitation')->unique()->nullable()
                  ->comment('Code unique pour inviter des utilisateurs');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Créer la boutique par défaut URSTORE Burkina
        DB::table('boutiques')->insert([
            'nom'             => 'URSTORE Burkina',
            'pays'            => 'Burkina Faso',
            'code_invitation' => 'URSTORE-BF-' . strtoupper(substr(md5('urstore-burkina'), 0, 6)),
            'actif'           => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('boutiques');
    }
};