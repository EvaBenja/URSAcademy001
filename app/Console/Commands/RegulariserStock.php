<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vente;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegulariserStock extends Command
{
    protected $signature   = 'stock:regulariser';
    protected $description = 'Annule les ventes non clôturées du jour et restitue le stock';

    public function handle()
    {
        $aujourd_hui = now()->format('Y-m-d');

        // Ventes du jour non terminées et non annulées
        $ventes = Vente::with(['items', 'livraison'])
            ->whereDate('date_vente', $aujourd_hui)
            ->whereNotIn('statut', ['terminee', 'annulee'])
            ->get();

        $nbRegularises = 0;

        foreach ($ventes as $vente) {
            // Ne pas annuler si la livraison est en_cours ou livree_attente_validation
            $statutLiv = $vente->livraison?->statut ?? null;
            if (in_array($statutLiv, ['en_cours', 'livree_attente_validation'])) {
                continue;
            }

            DB::transaction(function () use ($vente, &$nbRegularises) {
                // Restituer le stock pour chaque item non encore régularisé
                foreach ($vente->items as $item) {
                    if ($item->stock_regularise) continue;

                    Produit::where('id', $item->produit_id)
                        ->increment('quantite_stock', $item->quantite);

                    $item->update(['stock_regularise' => true]);
                }

                // Annuler la vente
                $vente->update([
                    'statut'           => 'annulee',
                    'motif_annulation' => 'Régularisation automatique minuit — non clôturée',
                ]);

                // Annuler la livraison si elle existe et est en attente
                if ($vente->livraison && in_array($vente->livraison->statut, ['en_attente', 'assignee'])) {
                    $vente->livraison->update(['statut' => 'annulee']);
                }

                Log::info("Stock régularisé — Vente #{$vente->id} annulée, stock restitué.");
                $nbRegularises++;
            });
        }

        $this->info("Régularisation terminée : {$nbRegularises} vente(s) annulée(s).");
        return 0;
    }
}