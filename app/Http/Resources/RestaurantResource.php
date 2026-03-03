<?php

namespace App\Http\Resources;

use App\Services\LimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RestaurantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'logo_url' => $this->logo_path
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($this->logo_path)
                : null,
            'slug' => $this->slug,
            'delivery_methods' => [
                'delivery' => (bool) $this->allows_delivery,
                'pickup' => (bool) $this->allows_pickup,
                'dine_in' => (bool) $this->allows_dine_in,
            ],
            'payment_methods' => PaymentMethodResource::collection(
                $this->paymentMethods->where('is_active', true)->values()
            ),
            'allows_delivery' => (bool) $this->allows_delivery,
            'allows_pickup' => (bool) $this->allows_pickup,
            'allows_dine_in' => (bool) $this->allows_dine_in,
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'schedules' => RestaurantScheduleResource::collection($this->whenLoaded('schedules')),
            'is_open' => $this->isCurrentlyOpen(),
            'orders_limit_reached' => app(LimitService::class)->isOrderLimitReached($this->resource),
        ];
    }
}
