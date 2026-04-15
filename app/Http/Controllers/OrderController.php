<?php

namespace App\Http\Controllers;

use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\OrderUpdated;
use App\Http\Requests\AdvanceOrderStatusRequest;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\StoreManualOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Services\DeliveryService;
use App\Services\LimitService;
use App\Services\OrderEditService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        private readonly OrderService $orderService,
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

        $limit = $this->limitService->summary($user->restaurant);

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
            'monthly_count' => $limit['used'],
            'orders_limit' => $limit['limit'],
            'limit_reason' => $limit['reason'],
            'limit_period' => $limit['period'],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $allowedBranches = $user->allowedBranchIds();

        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('id', $allowedBranches))
            ->get(['id', 'name', 'address', 'latitude', 'longitude']);

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

        $paymentMethods = PaymentMethod::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get(['id', 'type', 'is_active']);

        $restaurant = $user->restaurant;
        $limit = $this->limitService->summary($restaurant);

        return Inertia::render('Orders/Create', [
            'branches' => $branches,
            'categories' => $categories,
            'promotions' => $promotions,
            'paymentMethods' => $paymentMethods,
            'mapsKey' => config('services.google_maps.key', ''),
            'allowsDelivery' => (bool) $restaurant->allows_delivery,
            'allowsPickup' => (bool) $restaurant->allows_pickup,
            'allowsDineIn' => (bool) $restaurant->allows_dine_in,
            'monthly_count' => $limit['used'],
            'orders_limit' => $limit['limit'],
            'limit_reason' => $limit['reason'],
            'limit_period' => $limit['period'],
        ]);
    }

    public function previewDelivery(Request $request, DeliveryService $deliveryService): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $restaurant = $request->user()->restaurant;

        if (! $restaurant->allows_delivery) {
            return response()->json(['error' => 'El restaurante no tiene entrega a domicilio activa.'], 422);
        }

        try {
            $result = $deliveryService->calculate((float) $data['latitude'], (float) $data['longitude'], $restaurant);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'in_coverage' => $result->isInCoverage,
            'branch' => ['id' => $result->branch->id, 'name' => $result->branch->name],
            'distance_km' => $result->distanceKm,
            'duration_minutes' => $result->durationMinutes,
            'delivery_cost' => $result->deliveryCost,
        ]);
    }

    public function store(StoreManualOrderRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();
        $restaurant = $user->restaurant;
        $validated = $request->validated();

        // Operator branch authorization: pickup/dine_in must be at an allowed branch.
        // Delivery is exempt because the backend (DeliveryService) chooses the branch.
        if ($validated['delivery_type'] !== 'delivery') {
            $allowed = $user->allowedBranchIds();
            if ($allowed !== null && ! in_array((int) $validated['branch_id'], $allowed, true)) {
                throw ValidationException::withMessages([
                    'branch_id' => ['No tienes permiso para crear pedidos en esta sucursal.'],
                ]);
            }
        }

        try {
            $result = $this->orderService->store($validated, $restaurant, 'manual', $user->id);
        } catch (\DomainException $e) {
            // Operational gate block (suspended, period expired, etc).
            if (str_starts_with($e->getMessage(), 'restaurant_not_operational:')) {
                $reason = substr($e->getMessage(), strlen('restaurant_not_operational:'));
                logger()->info('operational_gate_blocked', [
                    'restaurant_id' => $restaurant->id,
                    'reason' => $reason,
                    'channel' => 'manual',
                    'user_id' => $user->id,
                ]);

                return back()->withInput()->with('error', \App\Support\BillingMessages::operational($restaurant, $reason));
            }

            // Differentiate limit errors by the actual reason.
            if ($e->getMessage() === 'monthly_limit_reached') {
                $reason = $this->limitService->summary($restaurant)['reason'];
                $message = match ($reason) {
                    'period_expired' => 'El periodo de pedidos ya expiró. Renueva tu plan o configura un nuevo periodo.',
                    'period_not_started' => 'El periodo de pedidos aún no inicia.',
                    default => 'Has alcanzado el límite de pedidos del periodo. Actualiza tu plan para crear más.',
                };

                return back()->withInput()->with('error', $message);
            }

            // Any other domain error (no branches, Google Maps failure, no coverage, etc).
            return back()->withInput()->with('error', $e->getMessage());
        }

        try {
            broadcast(new OrderCreated($result->order))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for manual order', ['order_id' => $result->order->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('orders.index')->with('success', 'Pedido creado correctamente.');
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['customer', 'branch', 'items.modifiers', 'events.user', 'audits.user']);

        $canViewFinancials = $request->user()->canViewFinancials();
        if (! $canViewFinancials) {
            foreach ($order->items as $item) {
                $item->makeHidden(['production_cost']);
                foreach ($item->modifiers as $mod) {
                    $mod->makeHidden(['production_cost']);
                }
            }
        }

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'mapsKey' => config('services.google_maps.key', ''),
            'is_admin' => $request->user()->isAdmin(),
            'can_view_financials' => $canViewFinancials,
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

        $result = DB::transaction(function () use ($request, $order): array {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if ($locked->status === 'cancelled') {
                return ['error' => 'No se puede avanzar un pedido cancelado.'];
            }

            $nextStatus = self::STATUS_TRANSITIONS[$locked->status] ?? null;

            if (! $nextStatus) {
                return ['error' => 'El pedido ya se encuentra en el estado final.'];
            }

            $previousStatus = $locked->status;
            $locked->update(['status' => $nextStatus]);

            OrderEvent::create([
                'order_id' => $locked->id,
                'user_id' => $request->user()->id,
                'action' => 'status_changed',
                'from_status' => $previousStatus,
                'to_status' => $nextStatus,
            ]);

            return ['success' => true, 'order' => $locked, 'previousStatus' => $previousStatus];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        $result['order']->load(['customer:id,name,phone', 'branch:id,name']);

        try {
            broadcast(new OrderStatusChanged($result['order'], $result['previousStatus']))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for status change', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', 'Estatus actualizado.');
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $result = DB::transaction(function () use ($request, $order): array {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            if (! $locked->isCancellable()) {
                return ['error' => 'Este pedido ya no puede ser cancelado.'];
            }

            $previousStatus = $locked->status;
            $locked->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->validated('cancellation_reason'),
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
            ]);

            // Release coupon use so the customer can reuse the coupon
            if ($locked->coupon_id) {
                \App\Models\CouponUse::where('order_id', $locked->id)->delete();
            }

            OrderEvent::create([
                'order_id' => $locked->id,
                'user_id' => $request->user()->id,
                'action' => 'cancelled',
                'from_status' => $previousStatus,
                'to_status' => 'cancelled',
                'metadata' => ['reason' => $request->validated('cancellation_reason')],
            ]);

            return ['success' => true, 'order' => $locked, 'previousStatus' => $previousStatus];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        $result['order']->load(['customer:id,name,phone', 'branch:id,name']);

        try {
            broadcast(new OrderCancelled($result['order'], $result['previousStatus']))->toOthers();
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
