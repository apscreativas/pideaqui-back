<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly StatisticsService $statistics) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $restaurant = $request->user()->load('restaurant')->restaurant;

        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : today()->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();

        $data = $this->statistics->getDashboardData($restaurant, $from, $to);
        $data['filters'] = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];

        return Inertia::render('Dashboard/Index', $data);
    }
}
