<?php

namespace App\Http\Controllers;

use App\Events\OrderCancelled;
use App\Events\OrderStatusChanged;
use App\Events\OrderUpdated;
use App\Http\Requests\AdvanceOrderStatusRequest;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Services\LimitService;
use App\Services\OrderEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderController extends Controller
{
    private const STATUS_TRANSITIONS = [
        'received' => 'preparing',
        'preparing' => 'on_the_way',
        'on_the_way' => 'delivered',
    ];

    public function __construct(
        private readonly LimitService $limitService,
        private readonly OrderEditService $orderEditService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $allowedBranches = $user->allowedBranchIds();

        $dateFrom = $request->date_from ?? now()->toDateString();
        $dateTo = $request->date_to ?? $dateFrom;

        $query = Order::with(['customer:id,name,phone', 'branch:id,name'])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->boolean('requires_invoice'), fn ($q) => $q->where('requires_invoice', true))
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('branch_id', $allowedBranches))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->latest();

        $orders = $query->get()->groupBy('status');

        // Operators only see their assigned branches in the filter dropdown.
        $branches = Branch::where('restaurant_id', $restaurantId)
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('id', $allowedBranches))
            ->get(['id', 'name']);

        return Inertia::render('Orders/Index', [
            'orders' => [
                'received' => $orders->get('received', collect())->values(),
                'preparing' => $orders->get('preparing', collect())->values(),
                'on_the_way' => $orders->get('on_the_way', collect())->values(),
                'delivered' => $orders->get('delivered', collect())->values(),
            ],
            'branches' => $branches,
            'filters' => [
                'branch_id' => $request->branch_id,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'requires_invoice' => $request->boolean('requires_invoice'),
            ],
            'monthly_count' => $this->limitService->orderCountInPeriod($user->restaurant),
            'orders_limit' => $user->restaurant->orders_limit,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['customer', 'branch', 'items.modifiers', 'events.user', 'audits.user']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'mapsKey' => config('services.google_maps.key', ''),
            'is_admin' => $request->user()->isAdmin(),
            'restaurantName' => $request->user()->restaurant->name,
        ]);
    }

    public function edit(Request $request, Order $order): Response
    {
        $this->authorize('edit', $order);

        $order->load(['customer', 'branch', 'items.modifiers']);

        $restaurantId = $request->user()->restaurant_id;

        // Active menu: categories with active products and their modifier groups
        $categories = Category::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with(['products' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'products' => $cat->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'production_cost' => $p->production_cost,
                    'image_url' => $p->image_url,
                    'modifier_groups' => $p->getAllModifierGroups(),
                ]),
            ]);

        // Active promotions
        $promotions = Promotion::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Promotion $p) => $p->isCurrentlyActive())
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'production_cost' => $p->production_cost,
                'image_url' => $p->image_url,
                'modifier_groups' => $p->getAllModifierGroups(),
            ])
            ->values();

        // Active payment methods
        $paymentMethods = PaymentMethod::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get(['id', 'type', 'is_active']);

        return Inertia::render('Orders/Edit', [
            'order' => $order,
            'categories' => $categories,
            'promotions' => $promotions,
            'paymentMethods' => $paymentMethods,
            'mapsKey' => config('services.google_maps.key', ''),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('edit', $order);

        try {
            $order = $this->orderEditService->update(
                $order,
                $request->validated(),
                $request->user(),
                $request->ip(),
            );
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return back()->with('error', $e->getMessage());
            }
            throw $e;
        }

        $order->load(['customer:id,name,phone', 'branch:id,name']);

        try {
            broadcast(new OrderUpdated($order))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for order update', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order->id)->with('success', 'Pedido actualizado correctamente.');
    }

    public function advanceStatus(AdvanceOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        if ($order->status === 'cancelled') {
            return back()->with('error', 'No se puede avanzar un pedido cancelado.');
        }

        $nextStatus = self::STATUS_TRANSITIONS[$order->status] ?? null;

        if (! $nextStatus) {
            return back()->with('error', 'El pedido ya se encuentra en el estado final.');
        }

        $previousStatus = $order->status;
        $order->update(['status' => $nextStatus]);

        // Audit trail
        OrderEvent::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'action' => 'status_changed',
            'from_status' => $previousStatus,
            'to_status' => $nextStatus,
        ]);

        $order->load(['customer:id,name,phone', 'branch:id,name']);

        try {
            broadcast(new OrderStatusChanged($order, $previousStatus))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for status change', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', 'Estatus actualizado.');
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $previousStatus = $order->status;
        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->validated('cancellation_reason'),
            'cancelled_at' => now(),
        ]);

        // Audit trail
        OrderEvent::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'action' => 'cancelled',
            'from_status' => $previousStatus,
            'to_status' => 'cancelled',
            'metadata' => ['reason' => $request->validated('cancellation_reason')],
        ]);

        $order->load(['customer:id,name,phone', 'branch:id,name']);

        try {
            broadcast(new OrderCancelled($order, $previousStatus))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for cancellation', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pedido cancelado.');
    }

    public function newCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowedBranches = $user->allowedBranchIds();

        $count = Order::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'received')
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('branch_id', $allowedBranches))
            ->count();

        return response()->json(['count' => $count]);
    }
}
