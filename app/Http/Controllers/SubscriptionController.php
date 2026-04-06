<?php

namespace App\Http\Controllers;

use App\Models\BillingAudit;
use App\Models\Plan;
use App\Services\LimitService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SubscriptionController extends Controller
{
    public function __construct(private readonly LimitService $limitService) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant->load(['plan', 'pendingPlan']);
        $plans = Plan::purchasable();
        $period = $this->limitService->getCurrentPeriod($restaurant);

        return Inertia::render('Settings/Subscription', [
            'restaurant' => [
                'plan' => $restaurant->plan,
                'status' => $restaurant->status,
                'grace_period_ends_at' => $restaurant->grace_period_ends_at?->toIso8601String(),
                'subscription_ends_at' => $restaurant->subscription_ends_at?->toIso8601String(),
                'orders_count' => $this->limitService->orderCountInPeriod($restaurant),
                'orders_limit' => $this->limitService->getOrdersLimit($restaurant),
                'branch_count' => $restaurant->branches()->count(),
                'max_branches' => $this->limitService->getMaxBranches($restaurant),
                'period_start' => $period ? $period['start']->toDateString() : null,
                'period_end' => $period ? $period['end']->toDateString() : null,
                'has_subscription' => $restaurant->subscribed('default'),
                'on_grace_period' => $restaurant->subscription('default')?->onGracePeriod() ?? false,
                'pending_plan' => $restaurant->pendingPlan,
                'pending_plan_effective_at' => $restaurant->pending_plan_effective_at?->toIso8601String(),
            ],
            'plans' => $plans,
            'intent' => null,
        ]);
    }

    public function checkout(Request $request): SymfonyResponse
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::query()->findOrFail($request->plan_id);
        $restaurant = $request->user()->restaurant;

        $priceId = $request->billing_cycle === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            return back()->with('error', 'Este plan no tiene un precio configurado en Stripe.');
        }

        $checkout = $restaurant->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('settings.subscription').'?success=1',
                'cancel_url' => route('settings.subscription').'?canceled=1',
            ]);

        return Inertia::location($checkout->url);
    }

    public function swap(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::query()->findOrFail($request->plan_id);
        $restaurant = $request->user()->restaurant->load('plan');

        if (! $restaurant->subscribed('default')) {
            return back()->with('error', 'No tienes una suscripción activa.');
        }

        $oldPlan = $restaurant->plan;
        $isDowngrade = $plan->orders_limit < $oldPlan->orders_limit
            || $plan->max_branches < $oldPlan->max_branches;

        if ($isDowngrade) {
            return $this->scheduleDowngrade($request, $restaurant, $plan, $oldPlan);
        }

        $priceId = $request->billing_cycle === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            return back()->with('error', 'Este plan no tiene un precio configurado en Stripe.');
        }

        return $this->applyUpgrade($request, $restaurant, $plan, $priceId, $oldPlan);
    }

    private function applyUpgrade(Request $request, $restaurant, Plan $plan, string $priceId, ?Plan $oldPlan): RedirectResponse|SymfonyResponse
    {
        $subscription = $restaurant->subscription('default');

        try {
            $subscription->swapAndInvoice($priceId);
        } catch (IncompletePayment $e) {
            // Payment needs confirmation (3D Secure, failed card, etc.)
            // Save plan change intent so we can apply it after payment
            $restaurant->assignPlan($plan);

            if ($restaurant->hasPendingDowngrade()) {
                $restaurant->clearPendingDowngrade();
            }

            BillingAudit::log(
                action: 'plan_changed',
                restaurantId: $restaurant->id,
                actorType: 'restaurant_admin',
                actorId: $request->user()->id,
                payload: [
                    'old_plan' => $oldPlan?->name,
                    'new_plan' => $plan->name,
                    'billing_cycle' => $request->billing_cycle,
                    'payment_status' => 'incomplete',
                ],
                ipAddress: $request->ip(),
            );

            return Inertia::location(
                $e->payment->latestInvoice()->hosted_invoice_url
                    ?? route('cashier.payment', [$e->payment->id, 'redirect' => route('settings.subscription')])
            );
        }

        // Sync billing period from Stripe after swap
        try {
            $stripeSubscription = $restaurant->stripe()->subscriptions->retrieve(
                $subscription->stripe_id,
                ['expand' => ['items']]
            );
            $firstItem = $stripeSubscription->items->data[0] ?? null;
            if ($firstItem) {
                $subscription->update([
                    'current_period_start' => Carbon::createFromTimestamp($firstItem->current_period_start),
                    'current_period_end' => Carbon::createFromTimestamp($firstItem->current_period_end),
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to sync billing period after upgrade: '.$e->getMessage());
        }

        $restaurant->assignPlan($plan);

        // Clear any pending downgrade since the upgrade supersedes it
        if ($restaurant->hasPendingDowngrade()) {
            $restaurant->clearPendingDowngrade();
        }

        // Get the proration amount from the latest invoice
        $latestInvoice = $restaurant->invoices()->first();
        $proratedAmount = $latestInvoice ? '$'.number_format($latestInvoice->rawTotal() / 100, 2) : null;

        BillingAudit::log(
            action: 'plan_changed',
            restaurantId: $restaurant->id,
            actorType: 'restaurant_admin',
            actorId: $request->user()->id,
            payload: [
                'old_plan' => $oldPlan?->name,
                'new_plan' => $plan->name,
                'billing_cycle' => $request->billing_cycle,
                'prorated_amount' => $proratedAmount,
            ],
            ipAddress: $request->ip(),
        );

        $message = "Plan cambiado a {$plan->name}.";
        if ($proratedAmount) {
            $message .= " Se cobró {$proratedAmount} MXN por diferencia prorrateada.";
        }

        return back()->with('success', $message);
    }

    private function scheduleDowngrade(Request $request, $restaurant, Plan $plan, ?Plan $oldPlan): RedirectResponse
    {
        $subscription = $restaurant->subscription('default');
        $periodEnd = $subscription->current_period_end
            ? Carbon::parse($subscription->current_period_end)
            : now()->endOfMonth();

        $restaurant->update([
            'pending_plan_id' => $plan->id,
            'pending_plan_effective_at' => $periodEnd,
        ]);

        BillingAudit::log(
            action: 'downgrade_scheduled',
            restaurantId: $restaurant->id,
            actorType: 'restaurant_admin',
            actorId: $request->user()->id,
            payload: [
                'current_plan' => $oldPlan?->name,
                'pending_plan' => $plan->name,
                'effective_at' => $periodEnd->toIso8601String(),
                'billing_cycle' => $request->billing_cycle,
            ],
            ipAddress: $request->ip(),
        );

        $formattedDate = $periodEnd->translatedFormat('j \\d\\e F \\d\\e Y');

        return back()->with('success', "Tu plan cambiará a {$plan->name} el {$formattedDate}. Seguirás gozando de los beneficios de {$oldPlan->name} hasta entonces.");
    }

    public function cancelPendingDowngrade(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant->load('pendingPlan');

        if (! $restaurant->hasPendingDowngrade()) {
            return back()->with('error', 'No hay cambio de plan pendiente.');
        }

        $pendingPlanName = $restaurant->pendingPlan?->name;
        $restaurant->clearPendingDowngrade();

        BillingAudit::log(
            action: 'downgrade_canceled',
            restaurantId: $restaurant->id,
            actorType: 'restaurant_admin',
            actorId: $request->user()->id,
            ipAddress: $request->ip(),
            payload: ['canceled_plan' => $pendingPlanName],
        );

        return back()->with('success', 'Cambio de plan cancelado. Mantienes tu plan actual.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;

        if (! $restaurant->subscribed('default')) {
            return back()->with('error', 'No tienes una suscripción activa.');
        }

        $restaurant->subscription('default')->cancel();

        $endsAt = $restaurant->subscription('default')->ends_at;

        $restaurant->transitionTo('canceled', [
            'subscription_ends_at' => $endsAt,
        ]);

        BillingAudit::log(
            action: 'subscription_canceled',
            restaurantId: $restaurant->id,
            actorType: 'restaurant_admin',
            actorId: $request->user()->id,
            payload: ['ends_at' => $endsAt?->toIso8601String()],
            ipAddress: $request->ip(),
        );

        return back()->with('success', 'Suscripción cancelada. Tu plan seguirá activo hasta el final del periodo pagado.');
    }

    public function resume(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $subscription = $restaurant->subscription('default');

        if (! $subscription || ! $subscription->onGracePeriod()) {
            return back()->with('error', 'No hay suscripción cancelada que reanudar.');
        }

        $subscription->resume();

        $restaurant->transitionTo('active', [
            'subscription_ends_at' => null,
        ]);

        BillingAudit::log(
            action: 'reactivated',
            restaurantId: $restaurant->id,
            actorType: 'restaurant_admin',
            actorId: $request->user()->id,
            ipAddress: $request->ip(),
        );

        return back()->with('success', 'Suscripción reactivada.');
    }

    public function portal(Request $request): RedirectResponse
    {
        return $request->user()->restaurant->redirectToBillingPortal(
            route('settings.subscription')
        );
    }
}
