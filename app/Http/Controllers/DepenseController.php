<?php
namespace App\Http\Controllers;
use App\Models\Depense;
use Illuminate\Http\Request;

class DepenseController extends Controller {
    public function index(Request $request) {
        $depenses = Depense::with('user')
            ->orderByDesc('date_depense')
            ->get();
        return response()->json($depenses);
    }

    public function store(Request $request) {
        $request->validate([
            'categorie'    => 'required|string',
            'motif'        => 'required|string',
            'montant'      => 'required|numeric|min:0',
            'date_depense' => 'required|date',
            'notes'        => 'nullable|string',
        ]);
        $depense = Depense::create([
            'user_id'      => $request->user()->id,
            'categorie'    => $request->categorie,
            'motif'        => $request->motif,
            'montant'      => $request->montant,
            'date_depense' => $request->date_depense,
            'notes'        => $request->notes,
        ]);
        return response()->json($depense->load('user'), 201);
    }

    public function update(Request $request, $id) {
        $depense = Depense::findOrFail($id);
        $request->validate([
            'categorie'    => 'sometimes|string',
            'motif'        => 'sometimes|string',
            'montant'      => 'sometimes|numeric|min:0',
            'date_depense' => 'sometimes|date',
            'notes'        => 'nullable|string',
        ]);
        $depense->update($request->all());
        return response()->json($depense->load('user'));
    }

    public function destroy($id) {
        Depense::findOrFail($id)->delete();
        return response()->json(['message' => 'Dépense supprimée']);
    }

    public function stats() {
        $total = Depense::sum('montant');
        $parCategorie = Depense::selectRaw('categorie, SUM(montant) as total, COUNT(*) as nombre')
            ->groupBy('categorie')
            ->get();
        return response()->json(['total' => $total, 'par_categorie' => $parCategorie]);
    }
}
