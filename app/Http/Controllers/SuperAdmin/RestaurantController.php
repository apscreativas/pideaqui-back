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
        $gracePlan = Plan::gracePlan();
        $graceDays = BillingSetting::getInt('initial_grace_period_days', 14);

        $restaurant = DB::transaction(function () use ($request, $data, $gracePlan, $graceDays): Restaurant {
            $restaurant = Restaurant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'access_token' => hash('sha256', Str::random(40)),
                'is_active' => true,
                'plan_id' => $gracePlan?->id,
                'status' => 'grace_period',
                'grace_period_ends_at' => now()->addDays($graceDays),
                'orders_limit' => $gracePlan?->orders_limit ?? 500,
                'orders_limit_start' => now()->startOfMonth(),
                'orders_limit_end' => now()->endOfMonth(),
                'max_branches' => $gracePlan?->max_branches ?? 1,
                'allows_delivery' => false,
                'allows_pickup' => true,
                'allows_dine_in' => false,
            ]);

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
                    'plan' => $gracePlan?->name ?? 'none',
                    'grace_days' => $graceDays,
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
        $restaurant->update($request->validated());

        return back()->with('success', 'Límites actualizados.');
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

    public function updatePlan(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $oldPlan = $restaurant->plan;
        $newPlan = Plan::query()->findOrFail($data['plan_id']);

        $restaurant->assignPlan($newPlan);

        BillingAudit::log(
            action: 'plan_changed',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')->id,
            payload: [
                'old_plan' => $oldPlan?->name,
                'new_plan' => $newPlan->name,
            ],
            ipAddress: $request->ip(),
        );

        return back()->with('success', "Plan cambiado a {$newPlan->name}.");
    }

    public function extendGrace(Request $request, Restaurant $restaurant): RedirectResponse
    {
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
}
