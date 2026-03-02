<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateRestaurantRequest;
use App\Http\Requests\SuperAdmin\UpdateRestaurantLimitsRequest;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
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

        $now = Carbon::now();

        $orderCounts = Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->selectRaw('restaurant_id, COUNT(*) as count')
            ->groupBy('restaurant_id')
            ->pluck('count', 'restaurant_id');

        $restaurants->each(function (Restaurant $restaurant) use ($orderCounts): void {
            $restaurant->monthly_orders_count = $orderCounts[$restaurant->id] ?? 0;
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
                'max_monthly_orders' => $data['max_monthly_orders'],
                'max_branches' => $data['max_branches'],
            ]);

            User::create([
                'restaurant_id' => $restaurant->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['password']),
            ]);

            PaymentMethod::insert([
                ['restaurant_id' => $restaurant->id, 'type' => 'cash', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
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
        $now = Carbon::now();

        $monthlyOrdersCount = Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('restaurant_id', $restaurant->id)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $branchCount = Branch::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('restaurant_id', $restaurant->id)
            ->count();

        $admin = User::query()
            ->where('restaurant_id', $restaurant->id)
            ->first(['id', 'name', 'email']);

        return Inertia::render('SuperAdmin/Restaurants/Show', [
            'restaurant' => $restaurant,
            'admin' => $admin,
            'monthly_orders_count' => $monthlyOrdersCount,
            'branch_count' => $branchCount,
        ]);
    }

    public function updateLimits(UpdateRestaurantLimitsRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update($request->validated());

        return back()->with('success', 'Límites actualizados.');
    }

    public function toggleActive(Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update(['is_active' => ! $restaurant->is_active]);

        $status = $restaurant->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Restaurante {$status}.");
    }
}
