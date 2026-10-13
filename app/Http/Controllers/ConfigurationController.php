<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigurationController extends Controller
{
    // Récupérer toutes les configurations
    public function index()
    {
        $configs = DB::table('configurations')->get();
        $result = [];
        foreach ($configs as $config) {
            $result[$config->cle] = $config->valeur;
        }
        return response()->json($result);
    }

    // Récupérer une configuration spécifique
    public function show($cle)
    {
        $config = DB::table('configurations')->where('cle', $cle)->first();
        if (!$config) {
            return response()->json(['message' => 'Configuration non trouvée'], 404);
        }
        return response()->json(['cle' => $config->cle, 'valeur' => $config->valeur]);
    }

    // Modifier une configuration (admin seulement)
    public function update(Request $request, $cle)
    {
        $request->validate([
            'valeur' => 'required|string',
        ]);

        $updated = DB::table('configurations')
            ->where('cle', $cle)
            ->update([
                'valeur'     => $request->valeur,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            // Créer si n'existe pas
            DB::table('configurations')->insert([
                'cle'        => $cle,
                'valeur'     => $request->valeur,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Configuration mise à jour',
            'cle'     => $cle,
            'valeur'  => $request->valeur,
        ]);
    }
}