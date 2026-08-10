<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

final class ReverseGeocoder
{
    public static function describe(float $latitude, float $longitude): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'AIMS-MarSU/1.0 (info@marsu.edu.ph)',
            ])->timeout(3)->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $latitude,
                'lon' => $longitude,
                'zoom' => 18,
            ]);

            if ($response->successful() && $response->json('display_name')) {
                return $response->json('display_name');
            }
        } catch (Throwable) {
            // Coordinates remain authoritative when reverse geocoding is offline.
        }

        return sprintf('Coordinates %.6f, %.6f', $latitude, $longitude);
    }
}
