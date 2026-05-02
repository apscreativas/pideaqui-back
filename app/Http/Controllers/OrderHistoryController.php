<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderHistoryController extends Controller
{
    /**
     * Reporte: historial de pedidos del restaurante en un rango de fechas con
     * sumatorias de Total / Costo / Utilidad sobre el rango completo (no
     * sobre la página actual). Visible para admin y operator (este último
     * sólo ve los pedidos de su propio branch — alineado con `OrderController`).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user()->load('restaurant');
        $restaurant = $user->restaurant;

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('restaurant_id', $restaurant->id)],
            'status' => ['nullable', 'in:all,delivered,cancelled'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
        ]);

        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : today()->subDays(6)->startOfDay();
        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : today()->endOfDay();
        $branchId = $request->integer('branch_id') ?: null;
        $status = $request->input('status', 'all');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 20);

        $allowedBranches = $user->allowedBranchIds();

        $base = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->when($status === 'delivered', fn ($q) => $q->where('status', 'delivered'))
            ->when($status === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('branch_id', $allowedBranches));

        $allForSums = (clone $base)
            ->with(['items.modifiers'])
            ->get();

        $sumTotal = (float) $allForSums->sum('total');
        $sumCost = (float) $allForSums->sum(fn (Order $o) => $o->productionCost());
        $sumProfit = (float) $allForSums->sum(fn (Order $o) => $o->profit());

        $paginated = (clone $base)
            ->with(['customer:id,name,phone', 'branch:id,name', 'items.modifiers'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'reference' => '#'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
                'created_at' => $order->created_at->toIso8601String(),
                'status' => $order->status,
                'customer_name' => $order->customer->name ?? null,
                'customer_phone' => $order->customer->phone ?? null,
                'branch_name' => $order->branch->name ?? null,
                'total' => (float) $order->total,
                'production_cost' => $order->productionCost(),
                'profit' => $order->profit(),
            ])
            ->withQueryString();

        return Inertia::render('Orders/History', [
            'orders' => $paginated,
            'summary' => [
                'count' => $allForSums->count(),
                'sum_total' => $sumTotal,
                'sum_cost' => $sumCost,
                'sum_profit' => $sumProfit,
            ],
            'branches' => Branch::where('restaurant_id', $restaurant->id)->get(['id', 'name']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $branchId,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }
}
