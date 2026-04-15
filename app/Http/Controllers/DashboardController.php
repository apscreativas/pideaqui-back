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
            'channel' => ['nullable', Rule::in(['orders', 'pos'])],
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
        $channel = $request->filled('channel') ? (string) $request->input('channel') : null;

        $data = $this->statistics->getDashboardData($restaurant, $from, $to, $effectiveBranches, $statuses, $minAmount, $maxAmount, $channel);

        // Cash metrics (revenue, breakdown, payment methods) are visible to both
        // admin and operator — operators need to know how much cash passed through
        // the register and what method it came via, to reconcile end-of-shift.
        // Profit/cost metrics (net_profit, expenses_total, real_profit) are
        // admin-only — sensitive business data.
        if (! $user->canViewProfitMetrics()) {
            unset($data['net_profit'], $data['expenses_total'], $data['real_profit']);
        }

        $data['can_view_financials'] = $user->canViewFinancials();
        $data['can_view_cash_metrics'] = $user->canViewCashMetrics();
        $data['can_view_profit_metrics'] = $user->canViewProfitMetrics();

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
            'channel' => $request->input('channel', ''),
        ];
        // Use the effective period from LimitService (resolves subscription →
        // grace_period → Stripe → manual in that order). The legacy
        // `orders_limit_start/end` columns on restaurants remain populated
        // after transitioning to subscription mode and would show stale dates.
        $period = app(\App\Services\LimitService::class)->getCurrentPeriod($restaurant);
        $data['orders_limit_start'] = $period ? $period['start']->toDateString() : null;
        $data['orders_limit_end'] = $period ? $period['end']->toDateString() : null;

        return Inertia::render('Dashboard/Index', $data);
    }
}
