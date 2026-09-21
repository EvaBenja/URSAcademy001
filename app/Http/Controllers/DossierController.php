<?php
namespace App\Http\Controllers;

use App\Models\DossierJournalier;
use App\Models\Livraison;
use Illuminate\Http\Request;

class DossierController extends Controller
{
    public function index(Request $request)
    {
        $user    = $request->user();
        $roleNom = $user->role?->nom ?? '';

        $query = DossierJournalier::with(['livreur', 'livraison', 'gestionnaire'])
            ->orderByDesc('date');

        // Livreur ne voit que ses propres dossiers
        if ($roleNom === 'livreur') {
            $query->where('livreur_id', $user->id);
        }

        return response()->json($query->get());
    }

    public function cloturer($id)
    {
        $dossier = DossierJournalier::findOrFail($id);
        $dossier->update(['statut' => 'cloture']);

        Livraison::where('id', $dossier->livraison_id)
            ->update(['statut' => 'terminee']);

        return response()->json([
            'message' => 'Dossier clôturé avec succès',
            'dossier' => $dossier->load(['livreur', 'livraison', 'gestionnaire']),
        ]);
    }
}
