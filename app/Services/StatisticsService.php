<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /** @return array<string, mixed> */
    public function getDashboardData(int $restaurantId, int $maxMonthlyOrders): array
    {
        return [
            'today_orders_count' => $this->todayCount($restaurantId),
            'yesterday_orders_count' => $this->yesterdayCount($restaurantId),
            'preparing_orders_count' => $this->preparingCount($restaurantId),
            'monthly_orders_count' => $this->monthlyCount($restaurantId),
            'max_monthly_orders' => $maxMonthlyOrders,
            'net_profit_month' => $this->netProfitMonth($restaurantId),
            'orders_by_branch' => $this->ordersByBranch($restaurantId),
            'recent_orders' => $this->recentOrders($restaurantId),
        ];
    }

    public function monthlyCount(int $restaurantId): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
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

        // Add modifier revenue (no production cost tracked for modifiers)
        $modifierRevenue = (float) DB::table('order_item_modifiers as oim')
            ->join('order_items as oi', 'oim.order_item_id', '=', 'oi.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.restaurant_id', $restaurantId)
            ->whereMonth('o.created_at', now()->month)
            ->whereYear('o.created_at', now()->year)
            ->sum('oim.price_adjustment');

        return round($baseProfit + $modifierRevenue, 2);
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
