<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvanceOrderStatusRequest;
use App\Models\Branch;
use App\Models\Order;
use App\Services\LimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const STATUS_TRANSITIONS = [
        'received' => 'preparing',
        'preparing' => 'on_the_way',
        'on_the_way' => 'delivered',
    ];

    public function __construct(private readonly LimitService $limitService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $restaurantId = $request->user()->restaurant_id;

        // Default to today when no date filters are provided
        $dateFrom = $request->date_from ?? now()->toDateString();
        $dateTo = $request->date_to ?? $dateFrom;

        $query = Order::with(['customer:id,name,phone', 'branch:id,name'])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest();

        $orders = $query->get()->groupBy('status');

        return Inertia::render('Orders/Index', [
            'orders' => [
                'received' => $orders->get('received', collect())->values(),
                'preparing' => $orders->get('preparing', collect())->values(),
                'on_the_way' => $orders->get('on_the_way', collect())->values(),
                'delivered' => $orders->get('delivered', collect())->values(),
            ],
            'branches' => Branch::all(['id', 'name']),
            'filters' => [
                'branch_id' => $request->branch_id,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'monthly_count' => $this->limitService->orderCountInPeriod($request->user()->restaurant),
            'orders_limit' => $request->user()->restaurant->orders_limit,
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['customer', 'branch', 'items.product', 'items.modifiers.modifierOption']);

        return Inertia::render('Orders/Show', ['order' => $order]);
    }

    public function advanceStatus(AdvanceOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $nextStatus = self::STATUS_TRANSITIONS[$order->status] ?? null;

        if (! $nextStatus) {
            return back()->with('error', 'El pedido ya se encuentra en el estado final.');
        }

        $order->update(['status' => $nextStatus]);

        return back()->with('success', 'Estatus actualizado.');
    }

    public function newCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => Order::where('restaurant_id', $request->user()->restaurant_id)
                ->where('status', 'received')
                ->count(),
        ]);
    }
}
