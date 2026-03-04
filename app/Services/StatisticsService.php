<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /** @return array<string, mixed> */
    public function getDashboardData(Restaurant $restaurant, Carbon $from, Carbon $to): array
    {
        $limitService = app(LimitService::class);
        $restaurantId = $restaurant->id;

        return [
            'orders_count' => $this->ordersCount($restaurantId, $from, $to),
            'preparing_orders_count' => $this->preparingCount($restaurantId),
            'monthly_orders_count' => $limitService->orderCountInPeriod($restaurant),
            'orders_limit' => $restaurant->orders_limit,
            'net_profit' => $this->netProfit($restaurantId, $from, $to),
            'revenue' => $this->revenue($restaurantId, $from, $to),
            'orders_by_branch' => $this->ordersByBranch($restaurantId, $from, $to),
            'recent_orders' => $this->recentOrders($restaurantId, $from, $to),
        ];
    }

    private function ordersCount(int $restaurantId, Carbon $from, Carbon $to): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function preparingCount(int $restaurantId): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'preparing')
            ->count();
    }

    private function revenue(int $restaurantId, Carbon $from, Carbon $to): float
    {
        return (float) Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');
    }

    private function netProfit(int $restaurantId, Carbon $from, Carbon $to): float
    {
        // Revenue - production cost (base items)
        $baseProfit = (float) DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->where('o.restaurant_id', $restaurantId)
            ->whereBetween('o.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(oi.unit_price * oi.quantity) - SUM(p.production_cost * oi.quantity), 0) as profit')
            ->value('profit');

        // Modifier profit = (price_adjustment - production_cost) × quantity
        $modifierProfit = (float) DB::table('order_item_modifiers as oim')
            ->join('order_items as oi', 'oim.order_item_id', '=', 'oi.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('modifier_options as mo', 'oim.modifier_option_id', '=', 'mo.id')
            ->where('o.restaurant_id', $restaurantId)
            ->whereBetween('o.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM((oim.price_adjustment - mo.production_cost) * oi.quantity), 0) as profit')
            ->value('profit');

        return round($baseProfit + $modifierProfit, 2);
    }

    /** @return Collection<int, array{id: int, name: string, count: int}> */
    private function ordersByBranch(int $restaurantId, Carbon $from, Carbon $to): Collection
    {
        return Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->withCount(['orders' => fn ($q) => $q->whereBetween('created_at', [$from, $to])])
            ->get()
            ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name, 'count' => $b->orders_count])
            ->sortByDesc('count')
            ->values();
    }

    /** @return Collection<int, mixed> */
    private function recentOrders(int $restaurantId, Carbon $from, Carbon $to): Collection
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['customer:id,name', 'branch:id,name'])
            ->latest()
            ->limit(20)
            ->get(['id', 'customer_id', 'branch_id', 'delivery_type', 'status', 'subtotal', 'delivery_cost', 'total', 'created_at']);
    }
}
