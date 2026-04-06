<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LimitService
{
    public function isOrderLimitReached(Restaurant $restaurant): bool
    {
        return $this->limitReason($restaurant) !== null;
    }

    /**
     * @return 'period_not_started'|'period_expired'|'limit_reached'|null
     */
    public function limitReason(Restaurant $restaurant): ?string
    {
        // Plan-based: limit is per billing cycle (calendar month)
        if ($restaurant->plan_id) {
            return $this->limitReasonFromPlan($restaurant);
        }

        // Legacy: limit is per configured date range
        return $this->limitReasonLegacy($restaurant);
    }

    public function orderCountInPeriod(Restaurant $restaurant): int
    {
        if ($restaurant->plan_id) {
            return $this->orderCountForBillingPeriod($restaurant);
        }

        return $this->orderCountLegacy($restaurant);
    }

    public function getOrdersLimit(Restaurant $restaurant): int
    {
        return $restaurant->getEffectiveOrdersLimit() ?? 0;
    }

    public function getMaxBranches(Restaurant $restaurant): int
    {
        return $restaurant->getEffectiveMaxBranches() ?? 1;
    }

    /**
     * @return array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon}|null
     */
    public function getCurrentPeriod(Restaurant $restaurant): ?array
    {
        if ($restaurant->plan_id) {
            $subscription = $restaurant->subscription('default');

            if ($subscription?->current_period_start && $subscription?->current_period_end) {
                return [
                    'start' => Carbon::parse($subscription->current_period_start),
                    'end' => Carbon::parse($subscription->current_period_end),
                ];
            }

            // Fallback to calendar month if period not synced yet
            Log::warning("Billing period not synced for restaurant {$restaurant->id}, using calendar month fallback");

            return [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ];
        }

        if ($restaurant->orders_limit_start && $restaurant->orders_limit_end) {
            return [
                'start' => $restaurant->orders_limit_start,
                'end' => $restaurant->orders_limit_end,
            ];
        }

        return null;
    }

    private function limitReasonFromPlan(Restaurant $restaurant): ?string
    {
        $limit = $restaurant->getEffectiveOrdersLimit();

        if (! $limit) {
            return null;
        }

        $count = $this->orderCountForBillingPeriod($restaurant);

        return $count >= $limit ? 'limit_reached' : null;
    }

    private function limitReasonLegacy(Restaurant $restaurant): ?string
    {
        if (! $restaurant->orders_limit_start || ! $restaurant->orders_limit_end) {
            return null;
        }

        if (now()->startOfDay()->lessThan($restaurant->orders_limit_start)) {
            return 'period_not_started';
        }

        if (now()->startOfDay()->greaterThan($restaurant->orders_limit_end)) {
            return 'period_expired';
        }

        $count = $this->orderCountLegacy($restaurant);

        return $count >= $restaurant->orders_limit ? 'limit_reached' : null;
    }

    private function orderCountForBillingPeriod(Restaurant $restaurant): int
    {
        $period = $this->getCurrentPeriod($restaurant);

        if (! $period) {
            return 0;
        }

        return Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [
                $period['start']->startOfDay(),
                $period['end']->endOfDay(),
            ])
            ->count();
    }

    private function orderCountLegacy(Restaurant $restaurant): int
    {
        if (! $restaurant->orders_limit_start || ! $restaurant->orders_limit_end) {
            return 0;
        }

        return Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [
                $restaurant->orders_limit_start->startOfDay(),
                $restaurant->orders_limit_end->endOfDay(),
            ])
            ->count();
    }
}
