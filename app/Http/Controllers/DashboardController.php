<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly StatisticsService $statistics) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        return Inertia::render('Dashboard/Index', $this->statistics->getDashboardData(
            restaurantId: $restaurant->id,
            maxMonthlyOrders: $restaurant->max_monthly_orders,
        ));
    }
}
