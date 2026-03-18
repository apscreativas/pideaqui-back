<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GoogleMapsService
{
    private const BASE_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    /**
     * Calculate driving distances from branch origins to the client destination.
     *
     * @param  float  $clientLat  Client (destination) latitude
     * @param  float  $clientLng  Client (destination) longitude
     * @param  Collection<int, array{latitude: float, longitude: float}>  $branches  Branch origins
     * @return array<int, array{distance_km: float, duration_minutes: int}>
     *
     * @throws ConnectionException|\RuntimeException
     */
    public function getDistances(float $clientLat, float $clientLng, Collection $branches): array
    {
        // Origin = branch (repartidor sale de la sucursal).
        // Destination = client (entrega en la ubicación del cliente).
        $originString = $branches
            ->map(fn (array $b) => "{$b['latitude']},{$b['longitude']}")
            ->implode('|');

        $response = Http::get(self::BASE_URL, [
            'origins' => $originString,
            'destinations' => "{$clientLat},{$clientLng}",
            'mode' => 'driving',
            'key' => config('services.google_maps.key'),
        ]);

        $json = $response->json();

        if (($json['status'] ?? '') !== 'OK') {
            throw new \RuntimeException('Google Distance Matrix API error: '.($json['status'] ?? 'unknown'));
        }

        // Each row = one origin (branch), each row has 1 element (the client destination).
        return collect($json['rows'])
            ->map(function (array $row): array {
                $element = $row['elements'][0] ?? [];

                if (($element['status'] ?? '') !== 'OK') {
                    return [
                        'distance_km' => PHP_FLOAT_MAX,
                        'duration_minutes' => 0,
                    ];
                }

                return [
                    'distance_km' => $element['distance']['value'] / 1000,
                    'duration_minutes' => (int) round($element['duration']['value'] / 60),
                ];
            })
            ->all();
    }
}
