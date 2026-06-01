<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class GeolocalisationController extends Controller
{
    // Livreur met à jour sa position
    public function updatePosition(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $user->update([
            'latitude'             => $request->latitude,
            'longitude'            => $request->longitude,
            'position_updated_at'  => now(),
        ]);

        return response()->json([
            'message'   => 'Position mise à jour',
            'latitude'  => $user->latitude,
            'longitude' => $user->longitude,
            'updated_at' => $user->position_updated_at,
        ]);
    }

    // Coordonnateur voit tous les livreurs avec leur position
    public function livreurs()
    {
        $livreurs = User::whereHas('role', function ($query) {
            $query->where('nom', 'livreur');
        })
        ->whereNotNull('latitude')
        ->select('id', 'name', 'email', 'latitude', 'longitude', 'position_updated_at')
        ->get();

        return response()->json($livreurs);
    }

    // Trouver le livreur le plus proche d'un point
    public function livreurLePlusProche(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $lat = $request->latitude;
        $lng = $request->longitude;

        $livreurs = User::whereHas('role', function ($query) {
            $query->where('nom', 'livreur');
        })
        ->whereNotNull('latitude')
        ->selectRaw("
            id, name, email, latitude, longitude, position_updated_at,
            (6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )) AS distance_km
        ", [$lat, $lng, $lat])
        ->orderBy('distance_km')
        ->first();

        if (!$livreurs) {
            return response()->json([
                'message' => 'Aucun livreur disponible'
            ], 404);
        }

        return response()->json($livreurs);
    }
}
