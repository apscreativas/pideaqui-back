<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;

class LimitService
{
    public function isOrderLimitReached(Restaurant $restaurant): bool
    {
        if (! $restaurant->orders_limit_start || ! $restaurant->orders_limit_end) {
            return false;
        }

        $count = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [
                $restaurant->orders_limit_start->startOfDay(),
                $restaurant->orders_limit_end->endOfDay(),
            ])
            ->count();

        return $count >= $restaurant->orders_limit;
    }

    public function orderCountInPeriod(Restaurant $restaurant): int
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
