<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Geocode an address to get latitude and longitude.
     *
     * @param string $address
     * @return array|null [latitude, longitude] or null if not found/failed
     */
    public function geocode(string $address): ?array
    {
        try {
            // Nominatim OSM search endpoint
            $response = Http::withHeaders([
                'User-Agent' => 'Lubis21-Logistics-App/1.0 (contact@lubis21.com)'
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'format' => 'json',
                'limit' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data) && isset($data[0])) {
                    return [
                        'latitude' => (float) $data[0]['lat'],
                        'longitude' => (float) $data[0]['lon'],
                    ];
                }
            }
            
            Log::warning("Geocoding failed or returned empty for address: {$address}. Status code: " . $response->status());
        } catch (\Exception $e) {
            Log::error("Geocoding exception for address '{$address}': " . $e->getMessage());
        }

        return null;
    }
}
