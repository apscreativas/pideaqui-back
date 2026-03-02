<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvanceOrderStatusRequest;
use App\Models\Branch;
use App\Models\Order;
use App\Services\StatisticsService;
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

    public function __construct(private readonly StatisticsService $statistics) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $restaurantId = $request->user()->restaurant_id;

        $query = Order::with(['customer:id,name,phone', 'branch:id,name'])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->date, fn ($q, $date) => $q->whereDate('created_at', $date))
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
            'filters' => $request->only(['branch_id', 'date']),
            'monthly_count' => $this->statistics->monthlyCount($restaurantId),
            'max_monthly_orders' => $request->user()->restaurant->max_monthly_orders,
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

    public function newCount(): JsonResponse
    {
        return response()->json([
            'count' => Order::where('status', 'received')->count(),
        ]);
    }
}
