<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use Carbon\Carbon;
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

        $restaurantId = $request->user()->restaurant_id;

        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : today()->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();

        $ordersQuery = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('created_at', [$from, $to])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id));

        if ($request->filled('statuses')) {
            $statuses = explode(',', $request->input('statuses'));
            $ordersQuery->whereIn('status', $statuses);
        }

        $orders = $ordersQuery
            ->with(['customer:id,name', 'branch:id,name'])
            ->get([
                'id', 'branch_id', 'customer_id', 'delivery_type', 'status',
                'total', 'latitude', 'longitude', 'created_at',
            ]);

        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get(['id', 'name', 'latitude', 'longitude']);

        // KPIs
        $allOrdersQuery = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id));

        $totalOrders = (clone $allOrdersQuery)->count();
        $activeOrders = (clone $allOrdersQuery)->whereIn('status', ['received', 'preparing', 'on_the_way'])->count();
        $deliveredOrders = (clone $allOrdersQuery)->where('status', 'delivered')->count();
        $cancelledOrders = (clone $allOrdersQuery)->where('status', 'cancelled')->count();
        $revenue = (float) (clone $allOrdersQuery)->where('status', 'delivered')->sum('total');

        return Inertia::render('Map/Index', [
            'orders' => $orders,
            'branches' => $branches,
            'kpis' => [
                'total' => $totalOrders,
                'active' => $activeOrders,
                'delivered' => $deliveredOrders,
                'cancelled' => $cancelledOrders,
                'revenue' => $revenue,
                'geolocated' => $orders->count(),
            ],
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
