<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateRestaurantRequest;
use App\Http\Requests\SuperAdmin\ResetAdminPasswordRequest;
use App\Http\Requests\SuperAdmin\UpdateRestaurantLimitsRequest;
use App\Models\BillingAudit;
use App\Models\BillingSetting;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\LimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function __construct(private readonly LimitService $limitService) {}

    public function index(Request $request): Response
    {
        $query = Restaurant::query()
            ->withCount([
                'branches as active_branch_count' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('is_active', true),
            ]);

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $restaurants = $query->latest()->paginate(20)->withQueryString();

        $restaurants->each(function (Restaurant $restaurant): void {
            $restaurant->period_orders_count = $this->limitService->orderCountInPeriod($restaurant);
        });

        return Inertia::render('SuperAdmin/Restaurants/Index', [
            'restaurants' => $restaurants,
            'filters' => $request->only('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Restaurants/Create');
    }

    public function store(CreateRestaurantRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isManual = ($data['billing_mode'] ?? 'grace') === 'manual';
        $data['slug'] = $this->generateUniqueSlug($data['name']);

        $restaurant = DB::transaction(function () use ($request, $data, $isManual): Restaurant {
            if ($isManual) {
                $restaurant = Restaurant::create([
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'access_token' => hash('sha256', Str::random(40)),
                    'is_active' => true,
                    'plan_id' => null,
                    'status' => 'active',
                    'orders_limit' => $data['orders_limit'],
                    'orders_limit_start' => $data['orders_limit_start'],
                    'orders_limit_end' => $data['orders_limit_end'],
                    'max_branches' => $data['max_branches'],
                    'allows_delivery' => false,
                    'allows_pickup' => true,
                    'allows_dine_in' => false,
                ]);
            } else {
                $gracePlan = Plan::gracePlan();
                $graceDays = BillingSetting::getInt('initial_grace_period_days', 14);

                $restaurant = Restaurant::create([
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'access_token' => hash('sha256', Str::random(40)),
                    'is_active' => true,
                    'billing_mode' => 'subscription',
                    'plan_id' => $gracePlan?->id,
                    'status' => 'grace_period',
                    'grace_period_ends_at' => now()->addDays($graceDays),
                    'orders_limit' => $gracePlan?->orders_limit ?? 50,
                    'orders_limit_start' => now()->startOfMonth(),
                    'orders_limit_end' => now()->endOfMonth(),
                    'max_branches' => $gracePlan?->max_branches ?? 1,
                    'allows_delivery' => false,
                    'allows_pickup' => true,
                    'allows_dine_in' => false,
                ]);
            }

            $user = new User([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['password'],
            ]);
            $user->role = 'admin';
            $user->restaurant_id = $restaurant->id;
            $user->save();

            PaymentMethod::insert([
                ['restaurant_id' => $restaurant->id, 'type' => 'cash', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => $restaurant->id, 'type' => 'terminal', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => $restaurant->id, 'type' => 'transfer', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);

            BillingAudit::log(
                action: 'restaurant_created',
                restaurantId: $restaurant->id,
                actorType: 'super_admin',
                actorId: $request->user('superadmin')->id,
                payload: [
                    'billing_mode' => $isManual ? 'manual' : 'grace',
                    'plan' => $restaurant->plan?->name ?? 'manual',
                ],
                ipAddress: $request->ip(),
            );

            return $restaurant;
        });

        return redirect()->route('super.restaurants.show', $restaurant)
            ->with('success', 'Restaurante creado exitosamente.');
    }

    public function show(Restaurant $restaurant): Response
    {
        $restaurant->load('plan');
        $ordersCount = $this->limitService->orderCountInPeriod($restaurant);

        $branchCount = Branch::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('restaurant_id', $restaurant->id)
            ->count();

        $admin = User::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('role', 'admin')
            ->first(['id', 'name', 'email']);

        $plans = Plan::query()
            ->where('is_default_grace', false)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('SuperAdmin/Restaurants/Show', [
            'restaurant' => $restaurant->makeVisible('access_token'),
            'admin' => $admin,
            'orders_count' => $ordersCount,
            'orders_limit' => $this->limitService->getOrdersLimit($restaurant),
            'branch_count' => $branchCount,
            'max_branches' => $this->limitService->getMaxBranches($restaurant),
            'plans' => $plans,
        ]);
    }

    public function updateLimits(UpdateRestaurantLimitsRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $wasSub = $restaurant->isSubscriptionMode();

        // Cancel Stripe subscription if switching from subscription to manual
        if ($wasSub) {
            $subscription = $restaurant->subscription('default');
            if ($subscription && ! $subscription->canceled()) {
                $subscription->cancelNow();
            }
        }

        $restaurant->transitionToManual($request->validated());

        if ($restaurant->status !== 'active' && $restaurant->status !== 'disabled') {
            $restaurant->transitionTo('active');
        }

        BillingAudit::log(
            action: $wasSub ? 'switched_to_manual' : 'limits_updated',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')->id,
            payload: $request->validated(),
            ipAddress: $request->ip(),
        );

        $message = $wasSub
            ? 'Restaurante cambiado a modo manual. Suscripción cancelada.'
            : 'Límites manuales actualizados.';

        return back()->with('success', $message);
    }

    public function regenerateToken(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update([
            'access_token' => hash('sha256', Str::random(40)),
        ]);

        return back()->with('success', 'Token regenerado exitosamente.');
    }

    public function toggleActive(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $previousStatus = $restaurant->status;

        if ($restaurant->status === 'disabled' || ! $restaurant->is_active) {
            $restaurant->transitionTo('active');

            BillingAudit::log(
                action: 'enabled',
                restaurantId: $restaurant->id,
                actorType: 'super_admin',
                actorId: $request->user('superadmin')->id,
                payload: ['previous_status' => $previousStatus],
                ipAddress: $request->ip(),
            );

            return back()->with('success', 'Restaurante activado.');
        }

        $restaurant->transitionTo('disabled');

        BillingAudit::log(
            action: 'disabled',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')->id,
            payload: ['previous_status' => $previousStatus],
            ipAddress: $request->ip(),
        );

        return back()->with('success', 'Restaurante desactivado.');
    }

    public function startGracePeriod(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $previousMode = $restaurant->billing_mode;
        $canceledStripe = false;

        // If the restaurant already has an active Stripe subscription, cancel
        // it before forcing grace. Otherwise Stripe would keep charging while
        // the local state says "grace_period". Use cancelNow() because we want
        // an immediate teardown — the SuperAdmin is intentionally resetting
        // the restaurant to a grace state.
        if ($restaurant->isSubscriptionMode() && $restaurant->subscribed('default')) {
            $subscription = $restaurant->subscription('default');

            try {
                $subscription->cancelNow();
                $canceledStripe = true;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to cancel Stripe subscription before starting grace', [
                    'restaurant_id' => $restaurant->id,
                    'error' => $e->getMessage(),
                ]);

                return back()->with('error', 'No se pudo cancelar la suscripción Stripe existente. Revisa los logs antes de reintentar.');
            }
        }

        $gracePlan = Plan::gracePlan();

        $restaurant->update([
            'billing_mode' => 'subscription',
            'plan_id' => $gracePlan?->id,
            'subscription_ends_at' => null,
            'pending_plan_id' => null,
            'pending_plan_effective_at' => null,
            'pending_billing_cycle' => null,
        ]);

        $restaurant->transitionTo('grace_period', [
            'grace_period_ends_at' => now()->addDays($data['days']),
        ]);

        BillingAudit::log(
            action: 'grace_period_started',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')->id,
            payload: [
                'days' => $data['days'],
                'previous_mode' => $previousMode,
                'stripe_subscription_canceled' => $canceledStripe,
            ],
            ipAddress: $request->ip(),
        );

        $msg = "Periodo de gracia iniciado ({$data['days']} días). El restaurante debe elegir su plan.";
        if ($canceledStripe) {
            $msg .= ' La suscripción Stripe anterior fue cancelada.';
        }

        return back()->with('success', $msg);
    }

    public function extendGrace(Request $request, Restaurant $restaurant): RedirectResponse
    {
        if ($restaurant->isSubscriptionMode() && $restaurant->subscribed('default')) {
            return back()->with('error', 'No se puede extender gracia de un restaurante con suscripción activa. Stripe controla el periodo de gracia.');
        }

        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $restaurant->transitionTo('grace_period', [
            'grace_period_ends_at' => now()->addDays($data['days']),
        ]);

        BillingAudit::log(
            action: 'grace_period_extended',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')->id,
            payload: [
                'days' => $data['days'],
                'new_ends_at' => $restaurant->grace_period_ends_at->toIso8601String(),
            ],
            ipAddress: $request->ip(),
        );

        return back()->with('success', "Periodo de gracia extendido {$data['days']} días.");
    }

    public function resetAdminPassword(ResetAdminPasswordRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $admin = User::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('role', 'admin')
            ->firstOrFail();

        $admin->update(['password' => $request->validated('password')]);

        return back()->with('success', 'Contraseña del administrador actualizada.');
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'restaurante';
        }

        $slug = $base;
        $suffix = 2;

        while (Restaurant::query()->withoutGlobalScope(TenantScope::class)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
