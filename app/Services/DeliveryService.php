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
    private const MAX_CANDIDATES = 3;

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
            throw new \DomainException('No active branches available for delivery calculation.');
        }

        // PASO 2 — Single branch: skip Haversine and Google entirely.
        if ($branches->count() === 1) {
            $branch = $branches->first();
            $distanceKm = $this->haversine->distance($clientLat, $clientLng, (float) $branch->latitude, (float) $branch->longitude);
            $durationMinutes = 0; // Unknown without Google — caller should handle

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

        $distances = $this->googleMaps->getDistances($clientLat, $clientLng, $destinations->values());

        $candidatesIndexed = $candidates->values();
        $bestIndex = 0;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($distances as $i => $d) {
            if ($d['distance_km'] < $bestDistance) {
                $bestDistance = $d['distance_km'];
                $bestIndex = $i;
            }
        }

        /** @var Branch $branch */
        $branch = $candidatesIndexed[$bestIndex]['branch'];
        $distanceKm = $distances[$bestIndex]['distance_km'];
        $durationMinutes = $distances[$bestIndex]['duration_minutes'];

        return $this->buildResult($restaurant, $branch, $distanceKm, $durationMinutes);
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
