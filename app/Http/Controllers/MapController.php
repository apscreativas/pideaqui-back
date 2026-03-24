<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'statuses' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $allowedBranches = $user->allowedBranchIds();

        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : today()->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();

        $branchScope = fn (Builder $q) => $q
            ->when($request->branch_id, fn ($q2, $id) => $q2->where('branch_id', $id))
            ->when($allowedBranches !== null, fn ($q2) => $q2->whereIn('branch_id', $allowedBranches));

        $ordersQuery = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('created_at', [$from, $to])
            ->tap($branchScope);

        if ($request->filled('statuses')) {
            $statuses = explode(',', $request->input('statuses'));
            $ordersQuery->whereIn('status', $statuses);
        }

        $orders = $ordersQuery
            ->with(['customer:id,name', 'branch:id,name'])
            ->get(['id', 'branch_id', 'customer_id', 'delivery_type', 'status', 'total', 'latitude', 'longitude', 'created_at']);

        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->when($allowedBranches !== null, fn (Builder $q) => $q->whereIn('id', $allowedBranches))
            ->get(['id', 'name', 'latitude', 'longitude']);

        $allOrdersQuery = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->tap($branchScope);

        $totalOrders = (clone $allOrdersQuery)->count();
        $activeOrders = (clone $allOrdersQuery)->whereIn('status', ['received', 'preparing', 'on_the_way'])->count();
        $deliveredOrders = (clone $allOrdersQuery)->where('status', 'delivered')->count();
        $cancelledOrders = (clone $allOrdersQuery)->where('status', 'cancelled')->count();

        $kpis = [
            'total' => $totalOrders,
            'active' => $activeOrders,
            'delivered' => $deliveredOrders,
            'cancelled' => $cancelledOrders,
            'geolocated' => $orders->count(),
        ];

        if ($user->isAdmin()) {
            $kpis['revenue'] = (float) (clone $allOrdersQuery)->where('status', 'delivered')->sum('total');
        }

        return Inertia::render('Map/Index', [
            'orders' => $orders,
            'branches' => $branches,
            'kpis' => $kpis,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $request->branch_id,
                'statuses' => $request->input('statuses', 'received,preparing,on_the_way,delivered'),
            ],
            'allBranches' => $branches,
            'mapsKey' => config('services.google_maps.key', ''),
        ]);
    }
}
