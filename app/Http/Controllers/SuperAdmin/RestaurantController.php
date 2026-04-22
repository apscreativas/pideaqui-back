<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateRestaurantRequest;
use App\Http\Requests\SuperAdmin\ResetAdminPasswordRequest;
use App\Http\Requests\SuperAdmin\UpdateRestaurantLimitsRequest;
use App\Http\Requests\SuperAdmin\UpdateRestaurantSlugRequest;
use App\Models\BillingAudit;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\LimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function __construct(private readonly LimitService $limitService) {}

    public function index(Request $request): Response
    {
        $query = Restaurant::query()
            ->withPeriodOrdersCount()
            ->withCount([
                'branches as active_branch_count' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('is_active', true),
            ]);

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $alert = $request->string('alert')->toString();

        $this->applyAlertFilter($query, $alert);

        // per_page restringido a un whitelist para evitar queries abusivas.
        $perPage = (int) $request->input('per_page', 20);
        if (! in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }

        $restaurants = $query->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('SuperAdmin/Restaurants/Index', [
            'restaurants' => $restaurants,
            'filters' => array_merge(
                $request->only('status', 'alert'),
                ['per_page' => $perPage],
            ),
        ]);
    }

    private function applyAlertFilter($query, string $alert): void
    {
        match ($alert) {
            // Paquete A — acciones urgentes
            'grace_expiring' => $query
                ->where('is_active', true)
                ->where('status', 'grace_period')
                ->whereBetween('grace_period_ends_at', [now(), now()->addDays(3)]),
            'billing_manual' => $query
                ->where('is_active', true)
                ->where('billing_mode', 'manual'),
            'new_this_week' => $query
                ->where('is_active', true)
                ->where('created_at', '>=', now()->subDays(7)),
            'orders_near_limit' => $query
                ->where('is_active', true)
                ->where('orders_limit', '>', 0)
                ->whereNotNull('orders_limit_start')
                ->whereNotNull('orders_limit_end')
                ->whereRaw('(SELECT COUNT(*) FROM orders
                    WHERE orders.restaurant_id = restaurants.id
                      AND orders.created_at BETWEEN restaurants.orders_limit_start AND restaurants.orders_limit_end
                )::float / NULLIF(restaurants.orders_limit, 0) >= 0.8'),

            // Estado general — mismos que los KPIs de la tab "Alertas"
            'past_due' => $query->where('status', 'past_due'),
            'grace_period' => $query->where('status', 'grace_period'),
            'suspended' => $query->where('status', 'suspended'),
            'no_subscription' => $query
                ->whereNull('stripe_id')
                ->where('status', '!=', 'disabled'),

            default => null,
        };
    }

    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Restaurants/Create');
    }

    public function store(
        CreateRestaurantRequest $request,
        \App\Services\Onboarding\RestaurantProvisioningService $provisioning,
    ): RedirectResponse {
        $data = $request->validated();

        $dto = new \App\Services\Onboarding\Dto\ProvisionRestaurantData(
            source: 'super_admin',
            restaurantName: $data['name'],
            adminName: $data['admin_name'],
            adminEmail: $data['admin_email'],
            adminPassword: $data['password'],
            billingMode: $data['billing_mode'] ?? 'grace',
            ordersLimit: $data['orders_limit'] ?? null,
            maxBranches: $data['max_branches'] ?? null,
            ordersLimitStart: isset($data['orders_limit_start'])
                ? \Carbon\Carbon::parse($data['orders_limit_start'])
                : null,
            ordersLimitEnd: isset($data['orders_limit_end'])
                ? \Carbon\Carbon::parse($data['orders_limit_end'])
                : null,
            actorId: $request->user('superadmin')->id,
            ipAddress: $request->ip(),
            slug: $data['slug'] ?? null,
        );

        $restaurant = $provisioning->provision($dto);

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
            'restaurant' => $restaurant,
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

    public function sendVerification(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $admin = User::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('role', 'admin')
            ->firstOrFail();

        $admin->notify(new VerifyEmailNotification);

        BillingAudit::log(
            action: 'verification_email_sent_manually',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')->id,
            payload: ['admin_email' => $admin->email],
            ipAddress: $request->ip(),
        );

        return back()->with('success', 'Correo de verificación enviado al administrador.');
    }

    /**
     * Rename a restaurant's public slug. Breaks any pre-existing QR codes
     * and shared links, so the UI requires an explicit confirmation step.
     * Audited on every change.
     */
    public function renameSlug(UpdateRestaurantSlugRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $old = $restaurant->slug;
        $new = $request->validated('slug');

        if ($old === $new) {
            return back()->with('success', 'El slug no cambió.');
        }

        $restaurant->update(['slug' => $new]);

        BillingAudit::log(
            action: 'restaurant_slug_renamed',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')->id,
            payload: ['old_slug' => $old, 'new_slug' => $new],
            ipAddress: $request->ip(),
        );

        return back()->with('success', "Slug actualizado de «{$old}» a «{$new}».");
    }
}
