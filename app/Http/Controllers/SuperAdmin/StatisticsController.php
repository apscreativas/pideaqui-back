<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    public function index(): Response
    {
        $now = Carbon::now();

        $ordersByDay = Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->selectRaw("DATE(created_at) as date, COUNT(*) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->map(fn ($row) => $row->total);

        $topRestaurants = Restaurant::query()
            ->withCount([
                'orders as monthly_orders_count' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereYear('created_at', $now->year)
                    ->whereMonth('created_at', $now->month),
            ])
            ->orderByDesc('monthly_orders_count')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'is_active']);

        return Inertia::render('SuperAdmin/Statistics', [
            'orders_by_day' => $ordersByDay,
            'top_restaurants' => $topRestaurants,
        ]);
    }
}
