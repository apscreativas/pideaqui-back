<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GoogleMapsService
{
    private const BASE_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    /**
     * @param  Collection<int, array{latitude: float, longitude: float}>  $destinations
     * @return array<int, array{distance_km: float, duration_minutes: int}>
     *
     * @throws ConnectionException|\RuntimeException
     */
    public function getDistances(float $clientLat, float $clientLng, Collection $destinations): array
    {
        $destinationString = $destinations
            ->map(fn (array $d) => "{$d['latitude']},{$d['longitude']}")
            ->implode('|');

        $response = Http::get(self::BASE_URL, [
            'origins' => "{$clientLat},{$clientLng}",
            'destinations' => $destinationString,
            'mode' => 'driving',
            'key' => config('services.google_maps.key'),
        ]);

        $json = $response->json();

        if (($json['status'] ?? '') !== 'OK') {
            throw new \RuntimeException('Google Distance Matrix API error: '.($json['status'] ?? 'unknown'));
        }

        return collect($json['rows'][0]['elements'])
            ->map(function (array $element): array {
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
