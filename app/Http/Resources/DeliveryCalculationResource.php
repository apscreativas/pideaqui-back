<?php

namespace App\Http\Resources;

use App\DTOs\DeliveryResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryResult */
class DeliveryCalculationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'branch_id' => $this->branch->id,
            'branch_name' => $this->branch->name,
            'branch_address' => $this->branch->address,
            'branch_whatsapp' => $this->branch->whatsapp,
            'distance_km' => $this->distanceKm,
            'duration_minutes' => $this->durationMinutes,
            'delivery_cost' => $this->deliveryCost,
            'is_in_coverage' => $this->isInCoverage,
            'is_open' => $this->isOpen,
            'schedule' => $this->when($this->schedule !== null, fn () => [
                'day_of_week' => $this->schedule->day_of_week,
                'opens_at' => $this->schedule->opens_at ? substr($this->schedule->opens_at, 0, 5) : null,
                'closes_at' => $this->schedule->closes_at ? substr($this->schedule->closes_at, 0, 5) : null,
                'is_closed' => $this->schedule->is_closed,
            ]),
        ];
    }
}
