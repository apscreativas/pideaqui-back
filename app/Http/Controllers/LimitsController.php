<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\LimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LimitsController extends Controller
{
    public function __construct(private readonly LimitService $limitService) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant.plan')->restaurant;

        $ordersCount = $this->limitService->orderCountInPeriod($restaurant);
        $branchCount = Branch::query()->where('restaurant_id', $restaurant->id)->count();
        $period = $this->limitService->getCurrentPeriod($restaurant);

        return Inertia::render('Settings/Limits', [
            'orders_count' => $ordersCount,
            'orders_limit' => $this->limitService->getOrdersLimit($restaurant),
            'orders_limit_start' => $period ? $period['start']->toDateString() : null,
            'orders_limit_end' => $period ? $period['end']->toDateString() : null,
            'branch_count' => $branchCount,
            'max_branches' => $this->limitService->getMaxBranches($restaurant),
            'plan_name' => $restaurant->plan?->name,
        ]);
    }
}
