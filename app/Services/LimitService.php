<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;

class LimitService
{
    public function isMonthlyLimitReached(Restaurant $restaurant): bool
    {
        $count = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return $count >= $restaurant->max_monthly_orders;
    }
}
