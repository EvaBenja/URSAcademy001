<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\Produit;
use App\Models\Livraison;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Statistiques globales
    public function stats()
    {
        return response()->json([
            'total_ventes'      => Vente::where('statut', 'validee')->sum('montant_total'),
            'nombre_ventes'     => Vente::count(),
            'ventes_en_attente' => Vente::where('statut', 'en_attente')->count(),
            'total_produits'    => Produit::count(),
            'stock_faible'      => Produit::where('quantite_stock', '<=', 5)->count(),
            'livraisons_en_cours' => Livraison::where('statut', 'en_cours')->count(),
            'livraisons_en_attente' => Livraison::where('statut', 'en_attente')->count(),
            'total_livreurs'    => User::whereHas('role', fn($q) => $q->where('nom', 'livreur'))->count(),
        ]);
    }

    // Graphique des ventes par période
    public function graphVentes(Request $request)
    {
        $periode = $request->get('periode', 'semaine');

        $ventes = Vente::where('statut', 'validee')
            ->selectRaw('DATE(date_vente) as date, SUM(montant_total) as total, COUNT(*) as nombre')
            ->when($periode === 'semaine', fn($q) => $q->where('date_vente', '>=', now()->subDays(7)))
            ->when($periode === 'mois', fn($q) => $q->where('date_vente', '>=', now()->subDays(30)))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($ventes);
    }

    // Dernières demandes de livraison
    public function demandesRecentes()
    {
        $demandes = Livraison::with(['livreur', 'gestionnaire'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json($demandes);
    }
}