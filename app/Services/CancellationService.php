<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\PosSale;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CancellationService
{
    /**
     * Aggregates only — the paginated list is served by `list()` so it can
     * scale independently of KPIs/breakdowns.
     *
     * @return array<string, mixed>
     */
    public function getData(Restaurant $restaurant, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $restaurantId = $restaurant->id;

        $orderCounts = $this->orderCounts($restaurantId, $from, $to, $branchId);
        $posCounts = $this->posCounts($restaurantId, $from, $to, $branchId);

        $cancelledCount = $orderCounts['cancelled'] + $posCounts['cancelled'];
        $totalCount = $orderCounts['total'] + $posCounts['total'];

        $reasons = $this->reasonsBreakdown($restaurantId, $from, $to, $branchId);

        return [
            'cancelled_count' => $cancelledCount,
            'total_orders_count' => $totalCount,
            'cancellation_rate' => $totalCount > 0 ? round(($cancelledCount / $totalCount) * 100, 1) : 0,
            'top_reason' => $reasons->first()['reason'] ?? null,
            'reasons_breakdown' => $reasons,
            'by_branch' => $this->byBranch($restaurantId, $from, $to, $branchId),
            'by_day' => $this->byDay($restaurantId, $from, $to, $branchId),
            'by_channel' => [
                'orders' => $orderCounts['cancelled'],
                'pos' => $posCounts['cancelled'],
            ],
        ];
    }

    /**
     * Paginated list of cancellations (orders + POS sales) merged and sorted
     * deterministically by `cancelled_at DESC, channel, id DESC`. Uses
     * `LengthAwarePaginator` so the UI can show page numbers and totals
     * without re-counting.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function list(
        Restaurant $restaurant,
        Carbon $from,
        Carbon $to,
        ?int $branchId,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        string $sortDir = 'desc',
    ): LengthAwarePaginator {
        $restaurantId = $restaurant->id;

        // Manifest (id + channel + cancelled_at + total). `total` is included
        // so we can sort the merged collection by it without re-querying the
        // underlying tables. Memory cost is ~32 bytes per row — fine up to
        // hundreds of thousands of cancellations.
        $orderKeys = $this->cancelledOrdersQuery($restaurantId, $from, $to, $branchId)
            ->get(['id', 'cancelled_at', 'total'])
            ->map(fn ($o) => [
                'channel' => 'orders',
                'id' => (int) $o->id,
                'cancelled_at' => $o->cancelled_at,
                'total' => (float) $o->total,
            ]);

        $posKeys = $this->cancelledPosQuery($restaurantId, $from, $to, $branchId)
            ->get(['id', 'cancelled_at', 'total'])
            ->map(fn ($s) => [
                'channel' => 'pos',
                'id' => (int) $s->id,
                'cancelled_at' => $s->cancelled_at,
                'total' => (float) $s->total,
            ]);

        // Sort strategy:
        //   - Primary: the requested column (cancelled_at | total).
        //   - Tie-breakers: channel ASC, id DESC (deterministic, identical
        //     to the legacy default so pagination stays stable even with
        //     identical values in the primary column).
        $primary = in_array($sortBy, ['cancelled_at', 'total'], true) ? $sortBy : 'cancelled_at';
        $directionMultiplier = $sortDir === 'asc' ? 1 : -1;

        $merged = $orderKeys
            ->concat($posKeys)
            ->sort(function ($a, $b) use ($primary, $directionMultiplier) {
                if ($primary === 'total') {
                    $av = $a['total'];
                    $bv = $b['total'];
                    if ($av !== $bv) {
                        return ($av <=> $bv) * $directionMultiplier;
                    }
                } else { // cancelled_at
                    $av = $a['cancelled_at']?->getTimestamp() ?? 0;
                    $bv = $b['cancelled_at']?->getTimestamp() ?? 0;
                    if ($av !== $bv) {
                        return ($av <=> $bv) * $directionMultiplier;
                    }
                }
                if ($a['channel'] !== $b['channel']) {
                    return $a['channel'] <=> $b['channel'];
                }

                return $b['id'] <=> $a['id'];
            })
            ->values();

        $total = $merged->count();
        $offset = max(0, ($page - 1) * $perPage);
        $slice = $merged->slice($offset, $perPage)->values();

        $orderIds = $slice->where('channel', 'orders')->pluck('id')->all();
        $posIds = $slice->where('channel', 'pos')->pluck('id')->all();

        $ordersById = $orderIds
            ? Order::query()
                ->whereIn('id', $orderIds)
                ->with(['customer:id,name,phone', 'branch:id,name'])
                ->get(['id', 'customer_id', 'branch_id', 'total', 'cancellation_reason', 'cancelled_at', 'created_at'])
                ->keyBy('id')
            : collect();

        $posById = $posIds
            ? PosSale::query()
                ->whereIn('id', $posIds)
                ->with(['cashier:id,name', 'branch:id,name'])
                ->get(['id', 'cashier_user_id', 'branch_id', 'ticket_number', 'total', 'cancellation_reason', 'cancelled_at', 'created_at'])
                ->keyBy('id')
            : collect();

        $rows = $slice->map(function (array $key) use ($ordersById, $posById) {
            if ($key['channel'] === 'orders') {
                $o = $ordersById->get($key['id']);
                if (! $o) {
                    return null;
                }

                return [
                    'id' => $o->id,
                    'channel' => 'orders',
                    'reference' => '#'.str_pad((string) $o->id, 4, '0', STR_PAD_LEFT),
                    'who' => $o->customer?->name,
                    'who_extra' => $o->customer?->phone,
                    'branch' => $o->branch ? ['id' => $o->branch->id, 'name' => $o->branch->name] : null,
                    'total' => $o->total,
                    'cancellation_reason' => $o->cancellation_reason,
                    'cancelled_at' => $o->cancelled_at?->toIso8601String(),
                    'created_at' => $o->created_at->toIso8601String(),
                ];
            }

            $s = $posById->get($key['id']);
            if (! $s) {
                return null;
            }

            return [
                'id' => $s->id,
                'channel' => 'pos',
                'reference' => $s->ticket_number,
                'who' => $s->cashier?->name,
                'who_extra' => 'Cajero',
                'branch' => $s->branch ? ['id' => $s->branch->id, 'name' => $s->branch->name] : null,
                'total' => $s->total,
                'cancellation_reason' => $s->cancellation_reason,
                'cancelled_at' => $s->cancelled_at?->toIso8601String(),
                'created_at' => $s->created_at->toIso8601String(),
            ];
        })->filter()->values()->all();

        return new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function cancelledOrdersQuery(int $restaurantId, Carbon $from, Carbon $to, ?int $branchId = null): Builder
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id));
    }

    private function cancelledPosQuery(int $restaurantId, Carbon $from, Carbon $to, ?int $branchId = null): Builder
    {
        return PosSale::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id));
    }

    /**
     * One aggregated query returning both total and cancelled counts for orders.
     *
     * @return array{total: int, cancelled: int}
     */
    private function orderCounts(int $restaurantId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $row = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->selectRaw(
                'COUNT(*) as total, '
                ."SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
        ];
    }

    /**
     * One aggregated query returning both total and cancelled counts for POS sales.
     *
     * @return array{total: int, cancelled: int}
     */
    private function posCounts(int $restaurantId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $row = PosSale::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->selectRaw(
                'COUNT(*) as total, '
                ."SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
        ];
    }

    /** @return Collection<int, array{reason: string, count: int, percentage: float}> */
    private function reasonsBreakdown(int $restaurantId, Carbon $from, Carbon $to, ?int $branchId): Collection
    {
        $orderRows = $this->cancelledOrdersQuery($restaurantId, $from, $to, $branchId)
            ->selectRaw("COALESCE(cancellation_reason, 'Sin motivo especificado') as reason, COUNT(*) as count")
            ->groupBy('reason')
            ->get();

        $posRows = $this->cancelledPosQuery($restaurantId, $from, $to, $branchId)
            ->selectRaw("COALESCE(cancellation_reason, 'Sin motivo especificado') as reason, COUNT(*) as count")
            ->groupBy('reason')
            ->get();

        $merged = collect()
            ->concat($orderRows)
            ->concat($posRows)
            ->groupBy('reason')
            ->map(fn ($group, $reason) => ['reason' => $reason, 'count' => $group->sum('count')])
            ->sortByDesc('count')
            ->values();

        $total = $merged->sum('count');

        return $merged->map(fn ($row) => [
            'reason' => $row['reason'],
            'count' => (int) $row['count'],
            'percentage' => $total > 0 ? round(($row['count'] / $total) * 100, 1) : 0,
        ])->values();
    }

    /**
     * Cancellations per branch for orders and POS sales combined. Two
     * aggregated queries (no N+1 loop) + in-memory merge.
     *
     * @return Collection<int, array{id: int, name: string, count: int}>
     */
    private function byBranch(int $restaurantId, Carbon $from, Carbon $to, ?int $branchId): Collection
    {
        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn (Builder $q, int $id) => $q->where('id', $id))
            ->get(['id', 'name'])
            ->keyBy('id');

        $orderCounts = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->selectRaw('branch_id, COUNT(*) as count')
            ->groupBy('branch_id')
            ->pluck('count', 'branch_id');

        $posCounts = PosSale::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->selectRaw('branch_id, COUNT(*) as count')
            ->groupBy('branch_id')
            ->pluck('count', 'branch_id');

        return $branches->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'count' => (int) ($orderCounts[$b->id] ?? 0) + (int) ($posCounts[$b->id] ?? 0),
        ])->values()->sortByDesc('count')->values();
    }

    /** @return Collection<int, array{date: string, count: int}> */
    private function byDay(int $restaurantId, Carbon $from, Carbon $to, ?int $branchId): Collection
    {
        $orderDays = $this->cancelledOrdersQuery($restaurantId, $from, $to, $branchId)
            ->selectRaw('DATE(cancelled_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();

        $posDays = $this->cancelledPosQuery($restaurantId, $from, $to, $branchId)
            ->selectRaw('DATE(cancelled_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();

        return collect()
            ->concat($orderDays)
            ->concat($posDays)
            ->groupBy('date')
            ->map(fn ($group, $date) => ['date' => $date, 'count' => (int) $group->sum('count')])
            ->sortBy('date')
            ->values();
    }
}
