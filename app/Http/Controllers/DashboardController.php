<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\Produit;
use App\Models\Livraison;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        // CA RÉEL = ventes dont la livraison est clôturée (terminee)
        $caReel = Vente::whereHas('livraison', fn($q) => $q->where('statut','terminee'))
            ->sum('montant_total');

        // CA EN COURS = ventes soumises non encore livrées
        $caEnCours = Vente::where('statut','!=','annulee')
            ->whereDoesntHave('livraison', fn($q) => $q->where('statut','terminee'))
            ->sum('montant_total');

        return response()->json([
            'total_ventes'          => (float) $caReel,
            'ca_en_cours'           => (float) $caEnCours,
            'nombre_ventes'         => Vente::where('statut','!=','annulee')->count(),
            'ventes_en_attente'     => Vente::where('statut','en_attente')->count(),
            'ventes_annulees'       => Vente::where('statut','annulee')->count(),
            'total_produits'        => Produit::count(),
            'stock_faible'          => Produit::where('quantite_stock','<=',5)->count(),
            'livraisons_en_cours'   => Livraison::where('statut','en_cours')->count(),
            'livraisons_en_attente' => Livraison::where('statut','en_attente')->count(),
            'livraisons_terminees'  => Livraison::where('statut','terminee')->count(),
            'total_livreurs'        => User::whereHas('role', fn($q) => $q->where('nom','livreur'))->count(),
        ]);
    }

    // Graphique CA journalier — basé sur les livraisons terminées (CA réel)
    // groupé par date pour que chaque jour ait ses propres données
    public function graphVentes(Request $request)
    {
        $periode = $request->get('periode', 'semaine');

        // CA réel par jour = ventes livrées terminées, groupées par date_vente
        $data = DB::table('ventes')
            ->join('livraisons', 'livraisons.vente_id', '=', 'ventes.id')
            ->where('livraisons.statut', 'terminee')
            ->when($periode === 'semaine', fn($q) => $q->where('ventes.date_vente', '>=', now()->subDays(6)->startOfDay()))
            ->when($periode === 'mois',   fn($q) => $q->where('ventes.date_vente', '>=', now()->subDays(29)->startOfDay()))
            ->selectRaw('DATE(ventes.date_vente) as date, SUM(ventes.montant_total) as total, COUNT(ventes.id) as nombre')
            ->groupByRaw('DATE(ventes.date_vente)')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    // Dernières demandes de livraison
    public function demandesRecentes()
    {
        $demandes = Livraison::with(['livreur','gestionnaire'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json($demandes);
    }
}
