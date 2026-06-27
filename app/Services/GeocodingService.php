<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeocodingService
{
    // Nominatim (OpenStreetMap) — gratuit, sans clé API.
    // Politique d'usage : max 1 req/sec, toujours fournir un User-Agent.
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * Convertit un texte d'adresse/quartier en coordonnées GPS.
     * Retourne ['lat' => float, 'lng' => float] ou null si échec.
     */
    public function geocode(string $query, string $pays = 'Burkina Faso'): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        // Cache 30 jours — un quartier ne change pas de position
        $cacheKey = 'geocode:' . md5(strtolower($query . '|' . $pays));
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($query, $pays) {
            try {
                $fullQuery = $query . ', ' . $pays;
                $response = Http::withHeaders([
                        'User-Agent' => 'URSStore/1.0 (livraison app)',
                    ])
                    ->timeout(5)
                    ->get(self::BASE_URL, [
                        'q'              => $fullQuery,
                        'format'         => 'json',
                        'limit'          => 1,
                        'countrycodes'   => 'bf', // Burkina Faso
                    ]);

                if (!$response->successful()) {
                    Log::warning("Géocodage échoué (HTTP {$response->status()}) pour: {$fullQuery}");
                    return null;
                }

                $results = $response->json();
                if (empty($results)) {
                    return null;
                }

                return [
                    'lat' => (float) $results[0]['lat'],
                    'lng' => (float) $results[0]['lon'],
                ];
            } catch (\Throwable $e) {
                Log::warning("Géocodage exception pour '{$query}': " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Distance en km entre deux points GPS (formule Haversine).
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
