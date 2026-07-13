<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    // Lister les commissions
    public function index(Request $request)
    {
        $user = $request->user()->load('role');
        $rolesAdmin = ['super_admin', 'gestionnaire', 'compta'];

        if (in_array($user->role->nom, $rolesAdmin)) {
            $commissions = Commission::with(['user', 'vente', 'produit'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $commissions = Commission::with(['user', 'vente', 'produit'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json($commissions);
    }

    // Stats commissions par vendeur
    public function stats(Request $request)
    {
        $user = $request->user()->load('role');
        $rolesAdmin = ['super_admin', 'gestionnaire', 'compta'];

        $query = Commission::with('user');

        if (!in_array($user->role->nom, $rolesAdmin)) {
            $query->where('user_id', $user->id);
        }

        $parVendeur = $query->get()
            ->groupBy('user_id')
            ->map(function ($items) {
                $vendeur = $items->first()->user;
                return [
                    'vendeur'             => $vendeur->name,
                    'email'               => $vendeur->email,
                    'total_commissions'   => $items->sum('montant_commission'),
                    'commissions_en_attente' => $items->where('statut', 'en_attente')->sum('montant_commission'),
                    'commissions_payees'  => $items->where('statut', 'payee')->sum('montant_commission'),
                    'nb_ventes'           => $items->count(),
                ];
            })->values();

        return response()->json([
            'total_global'    => Commission::sum('montant_commission'),
            'total_en_attente'=> Commission::where('statut', 'en_attente')->sum('montant_commission'),
            'total_paye'      => Commission::where('statut', 'payee')->sum('montant_commission'),
            'par_vendeur'     => $parVendeur,
        ]);
    }

    // Valider une commission
    public function valider($id)
    {
        $commission = Commission::findOrFail($id);
        $commission->update(['statut' => 'validee']);
        return response()->json($commission->load(['user', 'vente', 'produit']));
    }

    // Marquer comme payée
    public function payer($id)
    {
        $commission = Commission::findOrFail($id);
        $commission->update(['statut' => 'payee']);
        return response()->json($commission->load(['user', 'vente', 'produit']));
    }
}