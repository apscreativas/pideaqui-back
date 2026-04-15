<?php

namespace App\Http\Controllers;

use App\Events\PosSaleCancelled;
use App\Events\PosSaleCreated;
use App\Events\PosSaleStatusChanged;
use App\Http\Requests\CancelPosSaleRequest;
use App\Http\Requests\RegisterPosPaymentRequest;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Services\PosSaleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(
        private readonly PosSaleService $sales,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PosSale::class);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $allowedBranches = $user->allowedBranchIds();

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:preparing,ready,paid,cancelled'],
            'payment_method' => ['nullable', 'in:cash,terminal,transfer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
            'sort_by' => ['nullable', 'in:created_at,total,ticket_number'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ]);

        $perPage = (int) $request->input('per_page', 20);

        // Sort: if the client doesn't supply sort_by we keep the historical
        // default (created_at DESC). The `id DESC` tie-breaker below is applied
        // unconditionally so pagination stays stable even when many rows share
        // the same value in the primary sort column.
        $sortBy = $request->input('sort_by');
        $sortDir = $request->input('sort_direction') === 'asc' ? 'asc' : 'desc';

        $dateFrom = $request->date_from ?? now()->toDateString();
        $dateTo = $request->date_to ?? $dateFrom;

        // Timestamp range (not whereDate) so the composite index
        // (restaurant_id, status, created_at) is actually used by the planner.
        $fromTs = Carbon::parse($dateFrom)->startOfDay();
        $toExclusiveTs = Carbon::parse($dateTo)->addDay()->startOfDay();

        // Catalog data needed for the New Sale modal.
        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('id', $allowedBranches))
            ->get(['id', 'name']);

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

        $paymentMethods = PaymentMethod::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get(['id', 'type']);

        // Shared scope for the list and KPIs. The `status` filter is intentionally
        // excluded here so the 4 KPI cards keep showing the full breakdown
        // (tickets/revenue/open/cancelled) even when the user narrows the list.
        $scope = fn () => PosSale::query()
            ->where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $fromTs)
            ->where('created_at', '<', $toExclusiveTs)
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('branch_id', $allowedBranches))
            ->when(
                $request->payment_method,
                fn ($q, $method) => $q->whereExists(fn ($sub) => $sub->from('pos_payments')
                    ->whereColumn('pos_payments.pos_sale_id', 'pos_sales.id')
                    ->where('pos_payments.payment_method_type', $method))
            );

        // Aggregated KPIs in a single query. CASE WHEN is portable across
        // PostgreSQL and SQLite (tests).
        $kpi = $scope()
            ->selectRaw(
                'COUNT(*) as tickets, '
                ."COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) as revenue, "
                ."SUM(CASE WHEN status IN ('preparing','ready') THEN 1 ELSE 0 END) as open_count, "
                ."SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count"
            )
            ->first();

        $totals = [
            'tickets' => (int) ($kpi->tickets ?? 0),
            'revenue' => round((float) ($kpi->revenue ?? 0), 2),
            'open_count' => (int) ($kpi->open_count ?? 0),
            'cancelled_count' => (int) ($kpi->cancelled_count ?? 0),
        ];

        // Offset pagination (LengthAwarePaginator) so the UI can show total,
        // current page, and a per-page selector. The composite index
        // (restaurant_id, status, created_at) already keeps COUNT + the
        // date-range scan fast. Order is stable by (created_at DESC, id DESC)
        // so two rows with identical timestamps don't swap between pages.
        //
        // Caveat: if new sales arrive while the user is paging, the rows on
        // later pages can shift by one. The frontend mitigates this by only
        // mutating the list from broadcasts when we are on page 1.
        $salesQuery = $scope()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with([
                'cashier:id,name',
                'branch:id,name',
                'items:id,pos_sale_id,quantity',
                'payments:id,pos_sale_id,amount,payment_method_type',
            ]);

        if ($sortBy) {
            $salesQuery->orderBy($sortBy, $sortDir);
        } else {
            // Historical default when no sort is requested.
            $salesQuery->orderByDesc('created_at');
        }

        // Deterministic tie-breaker — newest id first regardless of primary
        // sort direction. Keeps pagination stable when many rows share the
        // same sort-column value (e.g. identical totals).
        $sales = $salesQuery->orderByDesc('id')->paginate($perPage)->withQueryString();

        $canViewFinancials = $user->canViewFinancials();

        // Products catalog exposes production_cost only for admins.
        if (! $canViewFinancials) {
            $categories = $categories->map(function ($cat) {
                $cat['products'] = $cat['products']->map(function ($p) {
                    unset($p['production_cost']);

                    return $p;
                });

                return $cat;
            });
        }

        return Inertia::render('Pos/Index', [
            'sales' => $sales,
            'branches' => $branches,
            'categories' => $categories,
            'paymentMethods' => $paymentMethods,
            'cashier' => ['id' => $user->id, 'name' => $user->name],
            'restaurantName' => $user->restaurant->name,
            'can_view_financials' => $canViewFinancials,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'branch_id' => $request->branch_id,
                'status' => $request->status,
                'payment_method' => $request->payment_method,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortBy ? $sortDir : null,
            ],
            'totals' => $totals,
        ]);
    }

    public function salesShow(PosSale $sale, Request $request): Response
    {
        $this->authorize('view', $sale);

        $sale->load(['cashier:id,name', 'branch:id,name', 'items.modifiers', 'payments.registeredBy:id,name']);

        $canViewFinancials = $request->user()->canViewFinancials();
        if (! $canViewFinancials) {
            $this->stripProductionCost($sale);
        }

        $paymentMethods = PaymentMethod::query()
            ->where('restaurant_id', $sale->restaurant_id)
            ->where('is_active', true)
            ->get(['id', 'type']);

        return Inertia::render('Pos/Sales/Show', [
            'sale' => $sale,
            'paymentMethods' => $paymentMethods,
            'restaurantName' => $request->user()->restaurant->name,
            'can_view_financials' => $canViewFinancials,
        ]);
    }

    /**
     * Strip `production_cost` from every item and modifier of a sale (or collection of sales).
     * Hides the attribute at serialization time so it never reaches the client JSON.
     */
    private function stripProductionCost(mixed $target): void
    {
        $sales = $target instanceof PosSale ? collect([$target]) : collect($target);

        foreach ($sales as $sale) {
            if (! $sale->relationLoaded('items')) {
                continue;
            }
            foreach ($sale->items as $item) {
                $item->makeHidden(['production_cost']);
                if ($item->relationLoaded('modifiers')) {
                    foreach ($item->modifiers as $mod) {
                        $mod->makeHidden(['production_cost']);
                    }
                }
            }
        }
    }

    public function store(StorePosSaleRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', PosSale::class);

        $user = $request->user();

        try {
            $sale = $this->sales->store($request->validated(), $user->restaurant, $user);
        } catch (\DomainException $e) {
            if (str_starts_with($e->getMessage(), 'restaurant_not_operational:')) {
                $reason = substr($e->getMessage(), strlen('restaurant_not_operational:'));
                logger()->info('operational_gate_blocked', [
                    'restaurant_id' => $user->restaurant_id,
                    'reason' => $reason,
                    'channel' => 'pos',
                    'user_id' => $user->id,
                ]);

                return back()->with('error', \App\Support\BillingMessages::operational($user->restaurant, $reason));
            }

            return back()->with('error', $e->getMessage());
        }

        try {
            broadcast(new PosSaleCreated($sale))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for POS sale creation', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('pos.sales.show', $sale->id)->with('success', 'Venta creada. Ticket '.$sale->ticket_number.'.');
    }

    public function cancel(CancelPosSaleRequest $request, PosSale $sale): RedirectResponse
    {
        $this->authorize('cancel', $sale);

        $previousStatus = $sale->status;
        $sale = $this->sales->cancel($sale, $request->validated('cancellation_reason'), $request->user());

        try {
            broadcast(new PosSaleCancelled($sale, $previousStatus))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for POS cancel', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', 'Venta cancelada.');
    }

    public function closePay(PosSale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $previousStatus = $sale->status;
        $sale = $this->sales->closePay($sale);

        try {
            broadcast(new PosSaleStatusChanged($sale, $previousStatus))->toOthers();
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed for POS pay', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', 'Venta cobrada. Ticket '.$sale->ticket_number.'.');
    }

    public function storePayment(RegisterPosPaymentRequest $request, PosSale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $previousStatus = $sale->status;
        $this->sales->registerPayment($sale, $request->validated(), $request->user());

        // Auto-close: when the payment covers the total, the service transitions
        // the sale to `paid`. Broadcast the status change so other clients update.
        $fresh = $sale->fresh();
        if ($previousStatus !== 'paid' && $fresh->status === 'paid') {
            $fresh->load(['cashier:id,name', 'branch:id,name']);
            try {
                broadcast(new PosSaleStatusChanged($fresh, $previousStatus))->toOthers();
            } catch (\Throwable $e) {
                logger()->warning('Broadcast failed for auto-close after payment', ['sale_id' => $fresh->id, 'error' => $e->getMessage()]);
            }

            return back()->with('success', 'Pago registrado · venta cobrada.');
        }

        return back()->with('success', 'Pago registrado.');
    }

    public function destroyPayment(PosSale $sale, PosPayment $payment): RedirectResponse
    {
        $this->authorize('update', $sale);

        if ($payment->pos_sale_id !== $sale->id) {
            abort(404);
        }

        $this->sales->removePayment($payment);

        return back()->with('success', 'Pago eliminado.');
    }
}
