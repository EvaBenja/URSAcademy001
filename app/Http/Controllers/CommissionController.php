<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('role');
        $rolesAdmin = ['super_admin', 'gestionnaire', 'compta'];

        $query = Commission::with(['user', 'vente', 'produit'])->orderBy('created_at', 'desc');

        if (!in_array($user->role->nom, $rolesAdmin)) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    public function stats(Request $request)
    {
        $user = $request->user()->load('role');
        $rolesAdmin = ['super_admin', 'gestionnaire', 'compta'];

        $query = Commission::with('user');
        if (!in_array($user->role->nom, $rolesAdmin)) {
            $query->where('user_id', $user->id);
        }

        $all = $query->get();

        $parVendeur = $all->groupBy('user_id')->map(function ($items) {
            $vendeur = $items->first()->user;
            $nom = trim(($vendeur->prenom ?? '') . ' ' . ($vendeur->nom ?? '')) ?: $vendeur->name ?? '—';
            return [
                'vendeur'                 => $nom,
                'email'                   => $vendeur->email,
                'total_commissions'       => $items->sum('montant_commission'),
                'commissions_validees'    => $items->where('statut', 'validee')->sum('montant_commission'),
                'commissions_payees'      => $items->where('statut', 'payee')->sum('montant_commission'),
                'nb_ventes'               => $items->count(),
            ];
        })->values();

        // Si c'est un vendeur, on retourne ses propres stats
        if (!in_array($user->role->nom, $rolesAdmin)) {
            $solde_total    = $all->whereIn('statut', ['validee'])->sum('montant_commission');
            $total_paye     = $all->where('statut', 'payee')->sum('montant_commission');
            return response()->json([
                'solde_disponible' => $solde_total,
                'total_paye'       => $total_paye,
                'total_global'     => $all->sum('montant_commission'),
                'nb_commissions'   => $all->count(),
                'par_vendeur'      => $parVendeur,
            ]);
        }

        return response()->json([
            'total_global'    => $all->sum('montant_commission'),
            'total_en_attente'=> $all->where('statut', 'en_attente')->sum('montant_commission'),
            'total_valide'    => $all->where('statut', 'validee')->sum('montant_commission'),
            'total_paye'      => $all->where('statut', 'payee')->sum('montant_commission'),
            'par_vendeur'     => $parVendeur,
        ]);
    }

    public function modifier(Request $request, $id)
    {
        $request->validate([
            'montant_commission' => 'required|numeric|min:0',
            'motif_modification' => 'required|string|min:5',
        ]);

        $commission = Commission::with(['user','produit','vente'])->findOrFail($id);

        $montantInitial = $commission->montant_initial ?? $commission->montant_commission;

        $commission->update([
            'montant_initial'    => $montantInitial,
            'montant_commission' => $request->montant_commission,
            'motif_modification' => $request->motif_modification,
            'modifie_par'        => $request->user()->id,
            'modifie_le'         => now(),
        ]);

        return response()->json($commission->load(['user','produit','vente','modifiePar']));
    }

    public function valider($id)
    {
        $commission = Commission::findOrFail($id);
        $commission->update(['statut' => 'validee']);
        return response()->json($commission->load(['user', 'vente', 'produit']));
    }

    public function payer($id)
    {
        $commission = Commission::findOrFail($id);
        $commission->update(['statut' => 'payee']);
        return response()->json($commission->load(['user', 'vente', 'produit']));
    }
}
