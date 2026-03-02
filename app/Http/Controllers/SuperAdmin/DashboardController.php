<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $now = Carbon::now();

        $activeRestaurants = Restaurant::query()->where('is_active', true)->count();

        $newRestaurantsThisMonth = Restaurant::query()
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $totalMonthlyOrders = Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $recentRestaurants = Restaurant::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'slug', 'is_active', 'created_at']);

        return Inertia::render('SuperAdmin/Dashboard', [
            'active_restaurants' => $activeRestaurants,
            'new_restaurants_this_month' => $newRestaurantsThisMonth,
            'total_monthly_orders' => $totalMonthlyOrders,
            'recent_restaurants' => $recentRestaurants,
        ]);
    }
}
