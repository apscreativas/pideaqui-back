<?php

namespace App\Http\Resources;

use App\Models\RestaurantSpecialDate;
use App\Services\LimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
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
            'today_schedule' => $this->resource->getResolvedScheduleForDate(Carbon::now(config('app.timezone'))),
            'closure_reason' => $this->resolveClosureReason(),
            'closure_label' => $this->resolveClosureLabel(),
            'upcoming_closures' => $this->resolveUpcomingClosures(),
            'orders_limit_reached' => app(LimitService::class)->isOrderLimitReached($this->resource),
            'limit_reason' => app(LimitService::class)->limitReason($this->resource),
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'default_product_image_url' => $this->default_product_image
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($this->default_product_image)
                : null,
            'text_color' => $this->text_color ?? self::resolveTextColor($this->primary_color),
        ];
    }

    private function resolveClosureReason(): ?string
    {
        if ($this->isCurrentlyOpen()) {
            return null;
        }

        $today = $this->resource->getResolvedScheduleForDate(Carbon::now(config('app.timezone')));

        return match ($today['source']) {
            'closed' => 'holiday',
            'special' => 'special_hours',
            default => null,
        };
    }

    private function resolveClosureLabel(): ?string
    {
        if ($this->isCurrentlyOpen()) {
            return null;
        }

        $today = $this->resource->getResolvedScheduleForDate(Carbon::now(config('app.timezone')));

        return $today['label'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveUpcomingClosures(): array
    {
        return RestaurantSpecialDate::query()
            ->where('restaurant_id', $this->resource->id)
            ->upcoming()
            ->limit(3)
            ->get()
            ->map(fn (RestaurantSpecialDate $sd) => [
                'date' => $sd->date->toDateString(),
                'type' => $sd->type,
                'label' => $sd->label,
                'opens_at' => $sd->opens_at ? substr($sd->opens_at, 0, 5) : null,
                'closes_at' => $sd->closes_at ? substr($sd->closes_at, 0, 5) : null,
                'is_recurring' => $sd->is_recurring,
            ])
            ->all();
    }

    /**
     * Auto-detect text color from background luminance.
     * Uses perceived brightness: 0.299R + 0.587G + 0.114B.
     */
    private static function resolveTextColor(?string $hex): string
    {
        if (! $hex || ! preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $hex)) {
            return 'dark'; // default bg (#f6f8f7) is light → dark text
        }

        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $brightness = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);

        return $brightness > 128 ? 'dark' : 'light';
    }
}
