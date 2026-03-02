<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LimitsController extends Controller
{
    public function __construct(private readonly StatisticsService $statistics) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        $monthlyCount = $this->statistics->monthlyCount($restaurant->id);
        $branchCount = Branch::query()->count();

        return Inertia::render('Settings/Limits', [
            'monthly_orders_count' => $monthlyCount,
            'max_monthly_orders' => $restaurant->max_monthly_orders,
            'branch_count' => $branchCount,
            'max_branches' => $restaurant->max_branches,
        ]);
    }
}
