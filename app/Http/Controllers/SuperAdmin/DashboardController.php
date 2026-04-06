<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingAudit;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $now = Carbon::now();

        return Inertia::render('SuperAdmin/Dashboard', [
            // Overview
            'mrr' => $this->calculateMrr(),
            'active_subscriptions' => DB::table('subscriptions')->where('stripe_status', 'active')->count(),
            'total_restaurants' => Restaurant::query()->count(),
            'new_this_month' => Restaurant::query()
                ->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->count(),
            'canceled_this_month' => BillingAudit::query()
                ->where('action', 'subscription_canceled')
                ->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->count(),
            'total_monthly_orders' => Order::query()
                ->withoutGlobalScope(TenantScope::class)
                ->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->count(),

            // By status
            'by_status' => Restaurant::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),

            // By plan
            'by_plan' => Plan::query()
                ->where('is_default_grace', false)
                ->withCount(['restaurants' => fn ($q) => $q->whereIn('status', ['active', 'past_due', 'grace_period', 'canceled'])])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $p) => [
                    'name' => $p->name,
                    'count' => $p->restaurants_count,
                    'monthly_price' => (float) $p->monthly_price,
                    'yearly_price' => (float) $p->yearly_price,
                    'revenue' => $this->planRevenue($p),
                ]),

            // Revenue breakdown
            'monthly_vs_annual' => $this->monthlyVsAnnual(),

            // Subscriptions timeline (last 30 days)
            'new_subs_by_day' => BillingAudit::query()
                ->where('action', 'subscription_started')
                ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date'),

            // Plan changes
            'recent_plan_changes' => BillingAudit::query()
                ->where('action', 'plan_changed')
                ->where('actor_type', '!=', 'system')
                ->with('restaurant:id,name')
                ->latest('created_at')
                ->take(10)
                ->get()
                ->map(fn ($a) => [
                    'restaurant' => $a->restaurant?->name ?? 'Eliminado',
                    'old_plan' => $a->payload['old_plan'] ?? '—',
                    'new_plan' => $a->payload['new_plan'] ?? '—',
                    'date' => $a->created_at->toIso8601String(),
                ]),

            // Alerts
            'alerts' => [
                'past_due' => Restaurant::query()->where('status', 'past_due')->count(),
                'grace_period' => Restaurant::query()->where('status', 'grace_period')->count(),
                'suspended' => Restaurant::query()->where('status', 'suspended')->count(),
                'no_subscription' => Restaurant::query()
                    ->whereNull('stripe_id')
                    ->where('status', '!=', 'disabled')
                    ->count(),
            ],

            // Recent events
            'recent_events' => BillingAudit::query()
                ->with('restaurant:id,name')
                ->latest('created_at')
                ->take(15)
                ->get()
                ->map(fn ($a) => [
                    'action' => $a->action,
                    'actor_type' => $a->actor_type,
                    'restaurant' => $a->restaurant?->name ?? '—',
                    'restaurant_id' => $a->restaurant_id,
                    'date' => $a->created_at->toIso8601String(),
                    'payload' => $a->payload,
                ]),

            // At-risk restaurants
            'at_risk_restaurants' => Restaurant::query()
                ->whereIn('status', ['past_due', 'grace_period', 'suspended'])
                ->with('plan:id,name')
                ->get(['id', 'name', 'status', 'plan_id', 'grace_period_ends_at'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'status' => $r->status,
                    'plan' => $r->plan?->name ?? '—',
                    'grace_ends' => $r->grace_period_ends_at?->toIso8601String(),
                ]),
        ]);
    }

    private function calculateMrr(): float
    {
        $activeSubscriptions = DB::table('subscriptions')
            ->where('stripe_status', 'active')
            ->get(['stripe_price']);

        $plans = Plan::query()->where('is_default_grace', false)->get();

        $mrr = 0;

        foreach ($activeSubscriptions as $sub) {
            $plan = $plans->first(function ($p) use ($sub) {
                return $p->stripe_monthly_price_id === $sub->stripe_price
                    || $p->stripe_yearly_price_id === $sub->stripe_price;
            });

            if (! $plan) {
                continue;
            }

            $mrr += $sub->stripe_price === $plan->stripe_yearly_price_id
                ? (float) $plan->yearly_price / 12
                : (float) $plan->monthly_price;
        }

        return round($mrr, 2);
    }

    private function planRevenue(Plan $plan): float
    {
        $monthlySubs = DB::table('subscriptions')
            ->where('stripe_status', 'active')
            ->where('stripe_price', $plan->stripe_monthly_price_id)
            ->count();

        $yearlySubs = DB::table('subscriptions')
            ->where('stripe_status', 'active')
            ->where('stripe_price', $plan->stripe_yearly_price_id)
            ->count();

        return round(
            ($monthlySubs * (float) $plan->monthly_price) + ($yearlySubs * (float) $plan->yearly_price / 12),
            2
        );
    }

    private function monthlyVsAnnual(): array
    {
        $plans = Plan::query()->where('is_default_grace', false)->get();
        $monthlyPriceIds = $plans->pluck('stripe_monthly_price_id')->filter()->all();
        $yearlyPriceIds = $plans->pluck('stripe_yearly_price_id')->filter()->all();

        return [
            'monthly' => DB::table('subscriptions')
                ->where('stripe_status', 'active')
                ->whereIn('stripe_price', $monthlyPriceIds)
                ->count(),
            'yearly' => DB::table('subscriptions')
                ->where('stripe_status', 'active')
                ->whereIn('stripe_price', $yearlyPriceIds)
                ->count(),
        ];
    }
}
