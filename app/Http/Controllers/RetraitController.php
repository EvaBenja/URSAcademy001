<?php

namespace App\Http\Controllers;

use App\Models\Retrait;
use Illuminate\Http\Request;

class RetraitController extends Controller
{
    // Lister les retraits (admin/gestionnaire/compta voient tout, vendeur voit les siens)
    public function index(Request $request)
    {
        $user = $request->user()->load('role');
        $rolesAdmin = ['super_admin', 'gestionnaire', 'compta'];

        if (in_array($user->role->nom, $rolesAdmin)) {
            $retraits = Retrait::with(['user', 'traitePar'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $retraits = Retrait::with(['user', 'traitePar'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json($retraits);
    }

    // Vendeur soumet une demande de retrait
    public function store(Request $request)
    {
        $request->validate([
            'montant'        => 'required|numeric|min:500',
            'numero_orange'  => 'required|string|min:8|max:15',
            'notes'          => 'nullable|string',
        ]);

        $retrait = Retrait::create([
            'user_id'       => $request->user()->id,
            'montant'       => $request->montant,
            'numero_orange' => $request->numero_orange,
            'notes'         => $request->notes,
            'statut'        => 'en_attente',
        ]);

        return response()->json($retrait->load('user'), 201);
    }

    // Approuver un retrait
    public function approuver(Request $request, $id)
    {
        $retrait = Retrait::findOrFail($id);

        $retrait->update([
            'statut'     => 'approuve',
            'traite_par' => $request->user()->id,
            'traite_le'  => now(),
        ]);

        return response()->json($retrait->load(['user', 'traitePar']));
    }

    // Refuser un retrait
    public function refuser(Request $request, $id)
    {
        $request->validate([
            'motif_refus' => 'required|string',
        ]);

        $retrait = Retrait::findOrFail($id);

        $retrait->update([
            'statut'      => 'refuse',
            'motif_refus' => $request->motif_refus,
            'traite_par'  => $request->user()->id,
            'traite_le'   => now(),
        ]);

        return response()->json($retrait->load(['user', 'traitePar']));
    }

    // Marquer comme payé
    public function payer(Request $request, $id)
    {
        $retrait = Retrait::findOrFail($id);

        $retrait->update([
            'statut'     => 'paye',
            'traite_par' => $request->user()->id,
            'traite_le'  => now(),
        ]);

        return response()->json($retrait->load(['user', 'traitePar']));
    }

    // Statistiques retraits
    public function stats(Request $request)
    {
        $total_demandes  = Retrait::count();
        $total_en_attente = Retrait::where('statut', 'en_attente')->count();
        $total_paye      = Retrait::where('statut', 'paye')->sum('montant');
        $total_approuve  = Retrait::where('statut', 'approuve')->sum('montant');

        return response()->json([
            'total_demandes'   => $total_demandes,
            'en_attente'       => $total_en_attente,
            'total_paye'       => $total_paye,
            'total_approuve'   => $total_approuve,
        ]);
    }
}