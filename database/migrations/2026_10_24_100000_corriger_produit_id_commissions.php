<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $commissions = DB::table('commissions')->get();

        foreach ($commissions as $commission) {
            $premierItem = DB::table('vente_items')
                ->where('vente_id', $commission->vente_id)
                ->orderBy('id')
                ->first();

            if ($premierItem && $premierItem->produit_id !== $commission->produit_id) {
                DB::table('commissions')
                    ->where('id', $commission->id)
                    ->update(['produit_id' => $premierItem->produit_id]);
            }
        }
    }

    public function down(): void {}
};