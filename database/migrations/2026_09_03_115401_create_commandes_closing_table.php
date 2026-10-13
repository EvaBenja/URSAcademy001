<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commandes_closing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boutique_id')->nullable();
            $table->string('shopify_order_id')->unique()->comment('ID de la commande Shopify');
            $table->string('shopify_order_number')->nullable()->comment('Numéro de commande Shopify');
            $table->string('client_nom')->nullable();
            $table->string('client_telephone')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_adresse')->nullable();
            $table->string('client_ville')->nullable();
            $table->string('client_pays')->nullable();
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->string('devise')->default('XOF');
            $table->json('produits')->nullable()->comment('Produits commandés (JSON)');
            $table->enum('statut', [
                'disponible',
                'prise',
                'a_relancer',
                'rejetee',
                'envoyee_livraison',
                'livree'
            ])->default('disponible');
            $table->string('motif_rejet')->nullable();
            $table->string('motif_relance')->nullable();
            $table->unsignedBigInteger('vendeur_id')->nullable()->comment('Vendeur qui a pris la commande');
            $table->unsignedBigInteger('vente_id')->nullable()->comment('Vente créée après confirmation');
            $table->timestamp('prise_le')->nullable();
            $table->timestamp('traitee_le')->nullable();
            $table->json('shopify_data')->nullable()->comment('Données brutes Shopify');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes_closing');
    }
};