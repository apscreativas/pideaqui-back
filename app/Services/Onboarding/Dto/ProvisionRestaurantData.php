<?php

namespace App\Services\Onboarding\Dto;

use Carbon\Carbon;

final readonly class ProvisionRestaurantData
{
    public function __construct(
        public string $source,
        public string $restaurantName,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
        public string $billingMode,
        public ?int $ordersLimit = null,
        public ?int $maxBranches = null,
        public ?Carbon $ordersLimitStart = null,
        public ?Carbon $ordersLimitEnd = null,
        public ?int $actorId = null,
        public ?string $ipAddress = null,
        public ?string $slug = null,
    ) {}
}
