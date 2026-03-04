<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateRestaurantRequest;
use App\Http\Requests\SuperAdmin\UpdateRestaurantLimitsRequest;
use App\Models\Branch;
use App\Models\PaymentMethod;
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

        $restaurant = DB::transaction(function () use ($data): Restaurant {
            $restaurant = Restaurant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'access_token' => hash('sha256', Str::random(40)),
                'is_active' => true,
                'orders_limit' => $data['orders_limit'],
                'orders_limit_start' => $data['orders_limit_start'],
                'orders_limit_end' => $data['orders_limit_end'],
                'max_branches' => $data['max_branches'],
                'allows_delivery' => false,
                'allows_pickup' => true,
                'allows_dine_in' => false,
            ]);

            $user = new User([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['password'],
            ]);
            $user->restaurant_id = $restaurant->id;
            $user->save();

            PaymentMethod::insert([
                ['restaurant_id' => $restaurant->id, 'type' => 'cash', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => $restaurant->id, 'type' => 'terminal', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => $restaurant->id, 'type' => 'transfer', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);

            return $restaurant;
        });

        return redirect()->route('super.restaurants.show', $restaurant)
            ->with('success', 'Restaurante creado exitosamente.');
    }

    public function show(Restaurant $restaurant): Response
    {
        $ordersCount = $this->limitService->orderCountInPeriod($restaurant);

        $branchCount = Branch::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('restaurant_id', $restaurant->id)
            ->count();

        $admin = User::query()
            ->where('restaurant_id', $restaurant->id)
            ->first(['id', 'name', 'email']);

        return Inertia::render('SuperAdmin/Restaurants/Show', [
            'restaurant' => $restaurant->makeVisible('access_token'),
            'admin' => $admin,
            'orders_count' => $ordersCount,
            'branch_count' => $branchCount,
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

    public function toggleActive(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update(['is_active' => ! $restaurant->is_active]);

        $status = $restaurant->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Restaurante {$status}.");
    }
}
