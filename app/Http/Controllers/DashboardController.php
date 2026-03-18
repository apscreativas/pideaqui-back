<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\StatisticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly StatisticsService $statistics) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurant = $user->load('restaurant')->restaurant;
        $allowedBranches = $user->allowedBranchIds();

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('restaurant_id', $restaurant->id)],
            'status' => ['nullable', 'string'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : today()->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : today()->endOfDay();

        // Determine effective branch filter: explicit selection OR operator's allowed branches.
        $selectedBranchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $effectiveBranches = $allowedBranches; // null = all (admin), array = operator's branches

        if ($selectedBranchId) {
            // Validate the selected branch is within the user's allowed scope.
            if ($allowedBranches !== null && ! in_array($selectedBranchId, $allowedBranches, true)) {
                abort(403, 'No tienes acceso a esta sucursal.');
            }
            $effectiveBranches = [$selectedBranchId];
        }

        // Parse status filter.
        $statuses = null;
        if ($request->filled('status')) {
            $valid = ['received', 'preparing', 'on_the_way', 'delivered', 'cancelled'];
            $statuses = array_values(array_intersect(explode(',', $request->input('status')), $valid));
            if (empty($statuses)) {
                $statuses = null;
            }
        }

        $minAmount = $request->filled('min_amount') ? (float) $request->input('min_amount') : null;
        $maxAmount = $request->filled('max_amount') ? (float) $request->input('max_amount') : null;

        $data = $this->statistics->getDashboardData($restaurant, $from, $to, $effectiveBranches, $statuses, $minAmount, $maxAmount);

        if ($user->isOperator()) {
            unset($data['net_profit'], $data['revenue']);
        }

        // Load branches for the selector (scoped to user's access).
        $branches = Branch::where('restaurant_id', $restaurant->id)
            ->when($allowedBranches !== null, fn ($q) => $q->whereIn('id', $allowedBranches))
            ->orderBy('name')
            ->get(['id', 'name']);

        $data['branches'] = $branches;
        $data['filters'] = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branch_id' => $request->input('branch_id', ''),
            'status' => $request->input('status', ''),
            'min_amount' => $request->input('min_amount', ''),
            'max_amount' => $request->input('max_amount', ''),
        ];
        $data['orders_limit_start'] = $restaurant->orders_limit_start?->toDateString();
        $data['orders_limit_end'] = $restaurant->orders_limit_end?->toDateString();

        return Inertia::render('Dashboard/Index', $data);
    }
}
