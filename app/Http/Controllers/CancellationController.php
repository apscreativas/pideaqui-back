<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use App\Services\CancellationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CancellationController extends Controller
{
    public function __construct(private readonly CancellationService $cancellations) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $restaurant = $request->user()->load('restaurant')->restaurant;

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('restaurant_id', $restaurant->id)],
        ]);

        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : today()->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();
        $branchId = $request->integer('branch_id') ?: null;

        $data = $this->cancellations->getData($restaurant, $from, $to, $branchId);

        $data['branches'] = Branch::where('restaurant_id', $restaurant->id)->get(['id', 'name']);
        $data['filters'] = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branch_id' => $branchId,
        ];

        return Inertia::render('Cancellations/Index', $data);
    }
}
