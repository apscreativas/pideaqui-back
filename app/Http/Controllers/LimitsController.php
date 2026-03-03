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
        $restaurant = $request->user()->load('restaurant')->restaurant;

        $ordersCount = $this->limitService->orderCountInPeriod($restaurant);
        $branchCount = Branch::query()->where('restaurant_id', $restaurant->id)->count();

        return Inertia::render('Settings/Limits', [
            'orders_count' => $ordersCount,
            'orders_limit' => $restaurant->orders_limit,
            'orders_limit_start' => $restaurant->orders_limit_start?->toDateString(),
            'orders_limit_end' => $restaurant->orders_limit_end?->toDateString(),
            'branch_count' => $branchCount,
            'max_branches' => $restaurant->max_branches,
        ]);
    }
}
