<?php

namespace App\DTOs;

use App\Models\Branch;
use App\Models\RestaurantSchedule;

readonly class DeliveryResult
{
    public function __construct(
        public Branch $branch,
        public float $distanceKm,
        public int $durationMinutes,
        public float $deliveryCost,
        public bool $isInCoverage,
        public bool $isOpen,
        public ?RestaurantSchedule $schedule = null,
    ) {}
}
