<?php

namespace App\Services;

use App\DTOs\DeliveryResult;
use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use Illuminate\Support\Collection;

class DeliveryService
{
    /** Maximum number of branches sent to Google Distance Matrix. */
    private const MAX_CANDIDATES = 1;

    public function __construct(
        private readonly HaversineService $haversine,
        private readonly GoogleMapsService $googleMaps,
    ) {}

    public function calculate(float $clientLat, float $clientLng, Restaurant $restaurant): DeliveryResult
    {
        // PASO 1 — Load active branches.
        /** @var Collection<int, Branch> $branches */
        $branches = Branch::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->get();

        // No active branches → cannot calculate.
        if ($branches->isEmpty()) {
            throw new \DomainException('No hay sucursales activas disponibles para calcular el envío.');
        }

        // PASO 2 — Single branch: use Google Maps for driving distance (skip pre-filter).
        if ($branches->count() === 1) {
            $branch = $branches->first();
            [$distanceKm, $durationMinutes] = $this->getDrivingDistance($clientLat, $clientLng, $branch);

            return $this->buildResult($restaurant, $branch, $distanceKm, $durationMinutes);
        }

        // PASO 3 — Haversine pre-filter: keep only the closest candidates.
        $candidates = $branches
            ->map(function (Branch $branch) use ($clientLat, $clientLng): array {
                return [
                    'branch' => $branch,
                    'haversine_km' => $this->haversine->distance(
                        $clientLat, $clientLng,
                        (float) $branch->latitude, (float) $branch->longitude,
                    ),
                ];
            })
            ->sortBy('haversine_km')
            ->take(self::MAX_CANDIDATES);

        // PASO 4 — Google Distance Matrix for candidates.
        $destinations = $candidates->map(fn (array $c) => [
            'latitude' => (float) $c['branch']->latitude,
            'longitude' => (float) $c['branch']->longitude,
        ]);

        try {
            $distances = $this->googleMaps->getDistances($clientLat, $clientLng, $destinations->values());
        } catch (\Throwable) {
            throw new \DomainException('No se pudo calcular la distancia de entrega. Intenta de nuevo más tarde.');
        }

        $candidatesIndexed = $candidates->values();
        $bestIndex = 0;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($distances as $i => $d) {
            if ($d['distance_km'] < $bestDistance) {
                $bestDistance = $d['distance_km'];
                $bestIndex = $i;
            }
        }

        if ($bestDistance >= PHP_FLOAT_MAX) {
            throw new \DomainException('No se pudo calcular la distancia de entrega. Intenta de nuevo más tarde.');
        }

        /** @var Branch $branch */
        $branch = $candidatesIndexed[$bestIndex]['branch'];
        $distanceKm = $distances[$bestIndex]['distance_km'];
        $durationMinutes = $distances[$bestIndex]['duration_minutes'];

        return $this->buildResult($restaurant, $branch, $distanceKm, $durationMinutes);
    }

    /**
     * Get driving distance via Google Maps. Throws DomainException if unavailable.
     *
     * @return array{0: float, 1: int}
     */
    private function getDrivingDistance(float $clientLat, float $clientLng, Branch $branch): array
    {
        try {
            $destinations = collect([['latitude' => (float) $branch->latitude, 'longitude' => (float) $branch->longitude]]);
            $results = $this->googleMaps->getDistances($clientLat, $clientLng, $destinations);
        } catch (\Throwable) {
            throw new \DomainException('No se pudo calcular la distancia de entrega. Intenta de nuevo más tarde.');
        }

        if ($results[0]['distance_km'] >= PHP_FLOAT_MAX) {
            throw new \DomainException('No se pudo calcular la distancia de entrega. Intenta de nuevo más tarde.');
        }

        return [$results[0]['distance_km'], $results[0]['duration_minutes']];
    }

    private function buildResult(Restaurant $restaurant, Branch $branch, float $distanceKm, int $durationMinutes): DeliveryResult
    {
        // PASO 5 & 6 — Delivery cost + coverage check.
        $ranges = $restaurant->deliveryRanges()->orderBy('sort_order')->get();
        $deliveryCost = 0.0;
        $isInCoverage = false;

        foreach ($ranges as $range) {
            if ($distanceKm >= (float) $range->min_km && $distanceKm < (float) $range->max_km) {
                $deliveryCost = (float) $range->price;
                $isInCoverage = true;
                break;
            }
        }

        // PASO 7 — Schedule validation (restaurant-level).
        [$isOpen, $schedule] = $this->checkSchedule($restaurant);

        return new DeliveryResult(
            branch: $branch,
            distanceKm: round($distanceKm, 2),
            durationMinutes: $durationMinutes,
            deliveryCost: $deliveryCost,
            isInCoverage: $isInCoverage,
            isOpen: $isOpen,
            schedule: $schedule,
        );
    }

    /**
     * @return array{0: bool, 1: ?RestaurantSchedule}
     */
    private function checkSchedule(Restaurant $restaurant): array
    {
        $dayOfWeek = now()->dayOfWeek;

        $schedule = RestaurantSchedule::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $schedule) {
            return [false, null];
        }

        if ($schedule->is_closed) {
            return [false, $schedule];
        }

        if (! $schedule->opens_at || ! $schedule->closes_at) {
            return [false, $schedule];
        }

        $now = now()->format('H:i:s');

        // Overnight schedule (e.g., 20:00–02:00): open if current time is after open OR before close.
        if ($schedule->opens_at > $schedule->closes_at) {
            $isOpen = $now >= $schedule->opens_at || $now <= $schedule->closes_at;
        } else {
            $isOpen = $now >= $schedule->opens_at && $now <= $schedule->closes_at;
        }

        return [$isOpen, $schedule];
    }
}
