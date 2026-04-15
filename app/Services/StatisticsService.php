<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Order;
use App\Models\PosSale;
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
     * @param  'orders'|'pos'|null  $channel  null = both; 'orders' = solo app externa; 'pos' = solo punto de venta
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
        ?string $channel = null,
    ): array {
        $limitService = app(LimitService::class);
        $restaurantId = $restaurant->id;

        $includeOrders = $channel !== 'pos';
        $includePos = $channel !== 'orders';

        $ordersRevenue = $includeOrders ? $this->revenue($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount) : 0.0;
        $posRevenue = $includePos ? $this->posRevenue($restaurantId, $from, $to, $branchIds) : 0.0;
        $ordersProfit = $includeOrders ? $this->netProfit($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount) : 0.0;
        $posProfit = $includePos ? $this->posNetProfit($restaurantId, $from, $to, $branchIds) : 0.0;
        $deliveryRevenue = $includeOrders ? $this->deliveryRevenue($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount) : 0.0;

        $byPayment = $includeOrders ? $this->revenueByPayment($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount) : ['cash' => 0.0, 'terminal' => 0.0, 'transfer' => 0.0];
        $posByPayment = $includePos ? $this->posRevenueByPayment($restaurantId, $from, $to, $branchIds) : ['cash' => 0.0, 'terminal' => 0.0, 'transfer' => 0.0];

        // Expenses are restaurant-level (no channel) — always computed for the period.
        $expensesTotal = $this->expensesTotal($restaurantId, $from, $to, $branchIds);
        $grossProfit = round($ordersProfit + $posProfit, 2);
        $realProfit = round($grossProfit - $expensesTotal, 2);

        return [
            // ── Counts (orders only — POS exposed separately) ────────────────
            'orders_count' => $includeOrders ? $this->ordersCount($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount) : 0,
            'preparing_orders_count' => $includeOrders ? $this->preparingCount($restaurantId, $branchIds) : 0,
            'pos_sales_count' => $includePos ? $this->posSalesCount($restaurantId, $from, $to, $branchIds) : 0,

            // ── Plan limit (orders only — POS NEVER counts) ──────────────────
            'monthly_orders_count' => $limitService->orderCountInPeriod($restaurant),
            // `getEffectiveOrdersLimit()` respects billing mode: returns the
            // plan's limit in subscription mode, the legacy field in manual.
            // Reading `$restaurant->orders_limit` directly leaked stale manual
            // values into the dashboard when a restaurant switched to trial.
            'orders_limit' => $restaurant->getEffectiveOrdersLimit(),

            // ── Unified revenue (orders.delivered + pos_sales.paid) ──────────
            'revenue' => round($ordersRevenue + $posRevenue, 2),
            'revenue_breakdown' => [
                'orders' => round($ordersRevenue, 2),
                'pos' => round($posRevenue, 2),
            ],

            // ── Profitability ────────────────────────────────────────────────
            // net_profit    = ingresos − costo producción (snapshot)
            // expenses      = suma de gastos operativos en el periodo
            // real_profit   = net_profit − expenses (utilidad después de gastos)
            'net_profit' => $grossProfit,
            'expenses_total' => round($expensesTotal, 2),
            'real_profit' => $realProfit,

            // ── Delivery revenue (informativo, NO ganancia del restaurante) ──
            // Monto cobrado al cliente por envíos. No se suma al net_profit
            // porque es pass-through (lo cobra el restaurante pero típicamente
            // lo paga al repartidor). Solo para visibilidad en el dashboard.
            'delivery_revenue' => round($deliveryRevenue, 2),

            // ── Unified payment-method breakdown ─────────────────────────────
            'revenue_by_payment' => [
                'cash' => round($byPayment['cash'] + $posByPayment['cash'], 2),
                'terminal' => round($byPayment['terminal'] + $posByPayment['terminal'], 2),
                'transfer' => round($byPayment['transfer'] + $posByPayment['transfer'], 2),
            ],

            // ── Per-branch order count (orders only — for chart compatibility)
            'orders_by_branch' => $this->ordersByBranch($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount),

            // ── Recent activity (orders + pos unified, with channel flag) ────
            'recent_orders' => $this->recentActivity($restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount, $channel),

            // ── Echo channel back to UI ──────────────────────────────────────
            'channel' => $channel,
        ];
    }

    /**
     * Sum of expenses in the period, filtered by branch if provided.
     * Uses `expense_date` (not created_at) since expenses are dated by the
     * user, not by row creation time.
     */
    private function expensesTotal(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds): float
    {
        return round((float) Expense::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $branchIds))
            ->sum('amount'), 2);
    }

    /** POS revenue grouped by payment method (sums each split). */
    private function posRevenueByPayment(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $rows = DB::table('pos_payments as pp')
            ->join('pos_sales as ps', 'pp.pos_sale_id', '=', 'ps.id')
            ->where('ps.restaurant_id', $restaurantId)
            ->where('ps.status', 'paid')
            ->whereBetween('ps.created_at', [$from, $to])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ps.branch_id', $branchIds))
            ->selectRaw('pp.payment_method_type as type, COALESCE(SUM(pp.amount), 0) as total')
            ->groupBy('pp.payment_method_type')
            ->pluck('total', 'type');

        return [
            'cash' => round((float) ($rows['cash'] ?? 0), 2),
            'terminal' => round((float) ($rows['terminal'] ?? 0), 2),
            'transfer' => round((float) ($rows['transfer'] ?? 0), 2),
        ];
    }

    /** POS net profit (paid sales) using snapshots in items + modifiers. */
    private function posNetProfit(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds): float
    {
        $base = (float) DB::table('pos_sale_items as psi')
            ->join('pos_sales as ps', 'psi.pos_sale_id', '=', 'ps.id')
            ->where('ps.restaurant_id', $restaurantId)
            ->where('ps.status', 'paid')
            ->whereBetween('ps.created_at', [$from, $to])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ps.branch_id', $branchIds))
            ->selectRaw('COALESCE(SUM(psi.unit_price * psi.quantity) - SUM(psi.production_cost * psi.quantity), 0) as profit')
            ->value('profit');

        $modifiers = (float) DB::table('pos_sale_item_modifiers as psim')
            ->join('pos_sale_items as psi', 'psim.pos_sale_item_id', '=', 'psi.id')
            ->join('pos_sales as ps', 'psi.pos_sale_id', '=', 'ps.id')
            ->where('ps.restaurant_id', $restaurantId)
            ->where('ps.status', 'paid')
            ->whereBetween('ps.created_at', [$from, $to])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ps.branch_id', $branchIds))
            ->selectRaw('COALESCE(SUM((psim.price_adjustment - psim.production_cost) * psi.quantity), 0) as profit')
            ->value('profit');

        return round($base + $modifiers, 2);
    }

    /**
     * Recent activity: latest orders + POS sales merged, tagged with `channel`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function recentActivity(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount, ?string $channel = null): Collection
    {
        $orders = collect();
        if ($channel !== 'pos') {
            $orders = $this->applyFilters(Order::query(), $restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount)
                ->with(['customer:id,name', 'branch:id,name'])
                ->latest()
                ->limit(20)
                ->get(['id', 'customer_id', 'branch_id', 'delivery_type', 'status', 'subtotal', 'delivery_cost', 'total', 'created_at'])
                ->map(fn ($o) => [
                    'channel' => 'orders',
                    'id' => $o->id,
                    'reference' => '#'.str_pad((string) $o->id, 4, '0', STR_PAD_LEFT),
                    'who' => $o->customer?->name,
                    'branch' => $o->branch ? ['id' => $o->branch->id, 'name' => $o->branch->name] : null,
                    'status' => $o->status,
                    'total' => $o->total,
                    'created_at' => $o->created_at->toIso8601String(),
                ]);
        }

        $pos = collect();
        if ($channel !== 'orders') {
            $pos = PosSale::query()
                ->with(['cashier:id,name', 'branch:id,name'])
                ->where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$from, $to])
                ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $branchIds))
                ->latest()
                ->limit(20)
                ->get(['id', 'cashier_user_id', 'branch_id', 'ticket_number', 'status', 'subtotal', 'total', 'created_at'])
                ->map(fn ($s) => [
                    'channel' => 'pos',
                    'id' => $s->id,
                    'reference' => $s->ticket_number,
                    'who' => $s->cashier?->name,
                    'branch' => $s->branch ? ['id' => $s->branch->id, 'name' => $s->branch->name] : null,
                    'status' => $s->status,
                    'total' => $s->total,
                    'created_at' => $s->created_at->toIso8601String(),
                ]);
        }

        return $orders->concat($pos)->sortByDesc('created_at')->take(20)->values();
    }

    /** Count of paid POS sales in the date range (filtered by branches if provided). */
    private function posSalesCount(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds): int
    {
        return PosSale::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $branchIds))
            ->count();
    }

    /** Sum of `total` of paid POS sales in the date range. */
    private function posRevenue(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds): float
    {
        return round((float) PosSale::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $branchIds))
            ->sum('total'), 2);
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
     * Total `delivery_cost` cobrado al cliente en orders delivered del periodo.
     * Informativo: no es ganancia del restaurante (pass-through al repartidor).
     */
    private function deliveryRevenue(int $restaurantId, Carbon $from, Carbon $to, ?array $branchIds, ?array $statuses, ?float $minAmount, ?float $maxAmount): float
    {
        $query = $this->applyFilters(Order::query(), $restaurantId, $from, $to, $branchIds, $statuses, $minAmount, $maxAmount);

        if ($statuses === null) {
            $query->where('status', 'delivered');
        }

        return (float) $query->sum('delivery_cost');
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
