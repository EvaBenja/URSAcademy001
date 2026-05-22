<?php

namespace App\Http\Controllers;

use App\Models\DossierJournalier;
use App\Models\Livraison;
use Illuminate\Http\Request;

class DossierController extends Controller
{
    // Lister tous les dossiers
    public function index()
    {
        $dossiers = DossierJournalier::with(['livreur', 'livraison'])
            ->orderByDesc('date')
            ->get();
        return response()->json($dossiers);
    }

    // Clôturer un dossier
    public function cloturer($id)
    {
        $dossier = DossierJournalier::findOrFail($id);
        $dossier->update(['statut' => 'cloture']);

        // Mettre à jour la livraison associée
        Livraison::where('id', $dossier->livraison_id)
            ->update(['statut' => 'terminee']);

        return response()->json([
            'message' => 'Dossier clôturé avec succès',
            'dossier' => $dossier
        ]);
    }
}