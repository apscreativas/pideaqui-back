<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /**
     * @param  list<int>|null  $branchIds  null = all branches (admin)
     * @param  list<string>|null  $statuses  null = all statuses
     * @return array<string, mixed>
     */
    public function getDashboardData(
        Restaurant $restaurant,
        Carbon $from,
        Carbon $to,
        ?array $branchIds = null,
        ?array $statuses = null,
        ?float $minAmount = null,
        ?float $maxAmount = null,
    ): array {
        $limitService = app(LimitService::class);
        $restaurantId = $restaurant->id;

        return [
            'orders_count' => $this->ordersCount($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount),
            'preparing_orders_count' => $this->preparingCount($restaurantId, $branchIds),
            'monthly_orders_count' => $limitService->orderCountInPeriod($restaurant),
            'orders_limit' => $restaurant->orders_limit,
            'net_profit' => $this->netProfit($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount),
            'revenue' => $this->revenue($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount),
            'revenue_by_payment' => $this->revenueByPayment($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount),
            'orders_by_branch' => $this->ordersByBranch($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount),
            'recent_orders' => $this->recentOrders($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount),
        ];
    }

    /**
     * Apply common filters to an order query.
     */
    private function applyFilters(
        Builder $query,
        int $restaurantId,
        Carbon $from,
        Carbon $to,
        ?array $branchIds,
        ?array $statuses,
        ?float $minAmount,
        ?float $maxAmount,
    ): Builder {
        $query->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to]);

        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        if ($minAmount !== null) {
            $query->where('total', '>=', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->where('total', '<=', $maxAmount);
        }

        return $query;
    }

    private function ordersCount(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount): int
    {
        return $this->applyFilters(Order::query(), $restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount)->count();
    }

    private function preparingCount(int $restaurantId, ?array $branchIds): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'preparing')
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $branchIds))
            ->count();
    }

    private function revenue(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount): float
    {
        $query = $this->applyFilters(Order::query(), $restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount);

        // Only sum delivered orders for revenue (unless status filter explicitly includes others).
        if ($statuses === null) {
            $query->where('status', 'delivered');
        }

        return (float) $query->sum('total');
    }

    /**
     * Revenue grouped by payment method (only delivered orders).
     *
     * @return array{cash: float, terminal: float, transfer: float}
     */
    private function revenueByPayment(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount): array
    {
        $query = $this->applyFilters(Order::query(), $restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount);

        if ($statuses === null) {
            $query->where('status', 'delivered');
        }

        $rows = $query->selectRaw('payment_method, SUM(total) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        return [
            'cash' => round((float) ($rows['cash'] ?? 0), 2),
            'terminal' => round((float) ($rows['terminal'] ?? 0), 2),
            'transfer' => round((float) ($rows['transfer'] ?? 0), 2),
        ];
    }

    private function netProfit(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount): float
    {
        $orderFilter = fn ($q) => $this->applyRawFilters($q, 'o', $restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount);

        // Only count delivered orders for profit (unless status filter explicitly includes others).
        $statusFilter = $statuses === null ? fn ($q) => $q->where('o.status', 'delivered') : fn ($q) => $q;

        $baseProfit = (float) DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->tap($orderFilter)
            ->tap($statusFilter)
            ->selectRaw('COALESCE(SUM(oi.unit_price * oi.quantity) - SUM(oi.production_cost * oi.quantity), 0) as profit')
            ->value('profit');

        $modifierProfit = (float) DB::table('order_item_modifiers as oim')
            ->join('order_items as oi', 'oim.order_item_id', '=', 'oi.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->tap($orderFilter)
            ->tap($statusFilter)
            ->selectRaw('COALESCE(SUM((oim.price_adjustment - oim.production_cost) * oi.quantity), 0) as profit')
            ->value('profit');

        $totalDiscounts = (float) DB::table('orders as o')
            ->tap($orderFilter)
            ->tap($statusFilter)
            ->selectRaw('COALESCE(SUM(o.discount_amount), 0) as discounts')
            ->value('discounts');

        return round($baseProfit + $modifierProfit - $totalDiscounts, 2);
    }

    /**
     * Apply filters on raw DB query builder (for join-based queries).
     */
    private function applyRawFilters(
        mixed $query,
        string $alias,
        int $restaurantId,
        Carbon $from,
        Carbon $to,
        ?array $branchIds,
        ?array $statuses,
        ?float $minAmount,
        ?float $maxAmount,
    ): void {
        $query->where("{$alias}.restaurant_id", $restaurantId)
            ->whereBetween("{$alias}.created_at", [$from, $to]);

        if ($branchIds !== null) {
            $query->whereIn("{$alias}.branch_id", $branchIds);
        }

        if ($statuses !== null) {
            $query->whereIn("{$alias}.status", $statuses);
        }

        if ($minAmount !== null) {
            $query->where("{$alias}.total", '>=', $minAmount);
        }

        if ($maxAmount !== null) {
            $query->where("{$alias}.total", '<=', $maxAmount);
        }
    }

    /** @return Collection<int, array{id: int, name: string, count: int}> */
    private function ordersByBranch(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount): Collection
    {
        return Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('id', $branchIds))
            ->withCount(['orders' => function ($q) use ($from, $to, $statuses, $minAmount, $maxAmount) {
                $q->whereBetween('created_at', [$from, $to]);
                if ($statuses !== null) {
                    $q->whereIn('status', $statuses);
                }
                if ($minAmount !== null) {
                    $q->where('total', '>=', $minAmount);
                }
                if ($maxAmount !== null) {
                    $q->where('total', '<=', $maxAmount);
                }
            }])
            ->get()
            ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name, 'count' => $b->orders_count])
            ->sortByDesc('count')
            ->values();
    }

    /** @return Collection<int, mixed> */
    private function recentOrders(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount): Collection
    {
        return $this->applyFilters(Order::query(), $restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount)
            ->with(['customer:id,name', 'branch:id,name'])
            ->latest()
            ->limit(20)
            ->get(['id', 'customer_id', 'branch_id', 'delivery_type', 'status', 'subtotal', 'delivery_cost', 'total', 'created_at']);
    }
}
