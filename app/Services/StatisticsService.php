<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /** @return array<string, mixed> */
    public function getDashboardData(Restaurant $restaurant): array
    {
        $limitService = app(LimitService::class);

        return [
            'today_orders_count' => $this->todayCount($restaurant->id),
            'yesterday_orders_count' => $this->yesterdayCount($restaurant->id),
            'preparing_orders_count' => $this->preparingCount($restaurant->id),
            'monthly_orders_count' => $limitService->orderCountInPeriod($restaurant),
            'orders_limit' => $restaurant->orders_limit,
            'net_profit_month' => $this->netProfitMonth($restaurant->id),
            'orders_by_branch' => $this->ordersByBranch($restaurant->id),
            'recent_orders' => $this->recentOrders($restaurant->id),
        ];
    }

    private function todayCount(int $restaurantId): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('created_at', today())
            ->count();
    }

    private function yesterdayCount(int $restaurantId): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('created_at', today()->subDay())
            ->count();
    }

    private function preparingCount(int $restaurantId): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'preparing')
            ->count();
    }

    private function netProfitMonth(int $restaurantId): float
    {
        // Revenue - production cost (base items)
        $baseProfit = (float) DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->where('o.restaurant_id', $restaurantId)
            ->whereMonth('o.created_at', now()->month)
            ->whereYear('o.created_at', now()->year)
            ->selectRaw('COALESCE(SUM(oi.unit_price * oi.quantity) - SUM(p.production_cost * oi.quantity), 0) as profit')
            ->value('profit');

        // Modifier profit = price_adjustment revenue - modifier production cost
        $modifierProfit = (float) DB::table('order_item_modifiers as oim')
            ->join('order_items as oi', 'oim.order_item_id', '=', 'oi.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('modifier_options as mo', 'oim.modifier_option_id', '=', 'mo.id')
            ->where('o.restaurant_id', $restaurantId)
            ->whereMonth('o.created_at', now()->month)
            ->whereYear('o.created_at', now()->year)
            ->selectRaw('COALESCE(SUM(oim.price_adjustment) - SUM(mo.production_cost), 0) as profit')
            ->value('profit');

        return round($baseProfit + $modifierProfit, 2);
    }

    /** @return Collection<int, array{id: int, name: string, count: int}> */
    private function ordersByBranch(int $restaurantId): Collection
    {
        return Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->withCount(['orders' => fn ($q) => $q->where('created_at', '>=', now()->subDays(7))])
            ->get()
            ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name, 'count' => $b->orders_count])
            ->sortByDesc('count')
            ->values();
    }

    /** @return Collection<int, mixed> */
    private function recentOrders(int $restaurantId): Collection
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['customer:id,name', 'branch:id,name'])
            ->latest()
            ->limit(10)
            ->get(['id', 'customer_id', 'branch_id', 'delivery_type', 'status', 'total', 'created_at']);
    }
}
