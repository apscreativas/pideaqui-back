<?php

namespace App\Http\Controllers;

use App\Models\BillingAudit;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    public function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'];
        $stripeCustomerId = $session['customer'] ?? null;
        $stripeSubscriptionId = $session['subscription'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $restaurant = Restaurant::query()->where('stripe_id', $stripeCustomerId)->first();

        if (! $restaurant) {
            Log::warning('Stripe checkout.session.completed: no restaurant for customer '.$stripeCustomerId);

            return;
        }

        // Try to resolve the price from the Cashier subscription first,
        // then fall back to Stripe API if the subscription record doesn't exist yet.
        $stripePriceId = null;

        $subscription = $restaurant->subscription('default');

        if ($subscription) {
            $stripePriceId = $subscription->stripe_price;
        } elseif ($stripeSubscriptionId) {
            // Subscription record may not exist yet — fetch from Stripe directly
            try {
                $stripeSubscription = \Laravel\Cashier\Cashier::stripe()->subscriptions->retrieve($stripeSubscriptionId);
                $stripePriceId = $stripeSubscription->items->data[0]->price->id ?? null;

                // Create the local subscription record that Cashier expects
                $firstItem = $stripeSubscription->items->data[0] ?? null;
                $subscription = $restaurant->subscriptions()->firstOrCreate(
                    ['type' => 'default', 'stripe_id' => $stripeSubscriptionId],
                    [
                        'stripe_status' => $stripeSubscription->status,
                        'stripe_price' => $stripePriceId,
                        'quantity' => 1,
                        'current_period_start' => $firstItem ? Carbon::createFromTimestamp($firstItem->current_period_start) : null,
                        'current_period_end' => $firstItem ? Carbon::createFromTimestamp($firstItem->current_period_end) : null,
                    ]
                );

                // Create subscription items so Cashier swap() works
                foreach ($stripeSubscription->items->data as $item) {
                    $subscription->items()->create([
                        'stripe_id' => $item->id,
                        'stripe_product' => $item->price->product,
                        'stripe_price' => $item->price->id,
                        'quantity' => $item->quantity ?? 1,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Stripe checkout: failed to retrieve subscription '.$stripeSubscriptionId.': '.$e->getMessage());

                return;
            }
        }

        if (! $stripePriceId) {
            Log::warning('Stripe checkout.session.completed: no price_id resolved for restaurant '.$restaurant->id);

            return;
        }

        $plan = Plan::query()
            ->where('stripe_monthly_price_id', $stripePriceId)
            ->orWhere('stripe_yearly_price_id', $stripePriceId)
            ->first();

        if ($plan) {
            $restaurant->assignPlan($plan);
            $restaurant->transitionToSubscription();
            $restaurant->transitionTo('active', [
                'grace_period_ends_at' => null,
            ]);

            BillingAudit::log(
                action: 'subscription_started',
                restaurantId: $restaurant->id,
                actorType: 'stripe',
                payload: [
                    'plan_name' => $plan->name,
                    'stripe_price_id' => $stripePriceId,
                    'checkout_session_id' => $session['id'] ?? null,
                ],
            );
        } else {
            Log::warning('Stripe checkout: no local plan matches price '.$stripePriceId);
        }
    }

    public function handleCustomerSubscriptionCreated(array $payload): void
    {
        // Let Cashier create the subscription record in the DB
        parent::handleCustomerSubscriptionCreated($payload);

        $subscription = $payload['data']['object'];
        $stripeCustomerId = $subscription['customer'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $restaurant = Restaurant::query()->where('stripe_id', $stripeCustomerId)->first();

        if (! $restaurant) {
            return;
        }

        // Sync billing period from Stripe
        $this->syncBillingPeriod($restaurant, $subscription);

        // Sync plan from the subscription's price
        $stripePriceId = $subscription['items']['data'][0]['price']['id'] ?? null;

        if ($stripePriceId) {
            $plan = Plan::query()
                ->where('stripe_monthly_price_id', $stripePriceId)
                ->orWhere('stripe_yearly_price_id', $stripePriceId)
                ->first();

            if ($plan && $restaurant->plan_id !== $plan->id) {
                $restaurant->assignPlan($plan);
                $restaurant->transitionTo('active', [
                    'grace_period_ends_at' => null,
                ]);

                BillingAudit::log(
                    action: 'subscription_started',
                    restaurantId: $restaurant->id,
                    actorType: 'stripe',
                    payload: [
                        'plan_name' => $plan->name,
                        'stripe_price_id' => $stripePriceId,
                        'source' => 'customer.subscription.created',
                    ],
                );
            }
        }
    }

    public function handleInvoicePaid(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $stripeCustomerId = $invoice['customer'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $restaurant = Restaurant::query()->where('stripe_id', $stripeCustomerId)->first();

        if (! $restaurant) {
            return;
        }

        // Apply pending downgrade if the new billing cycle just started
        $this->applyPendingDowngrade($restaurant);

        $previousStatus = $restaurant->status;

        if (in_array($previousStatus, ['past_due', 'grace_period', 'suspended'])) {
            $restaurant->transitionTo('active', [
                'grace_period_ends_at' => null,
            ]);

            BillingAudit::log(
                action: $previousStatus === 'suspended' ? 'reactivated' : 'payment_succeeded',
                restaurantId: $restaurant->id,
                actorType: 'stripe',
                payload: [
                    'previous_status' => $previousStatus,
                    'invoice_id' => $invoice['id'] ?? null,
                    'amount_paid' => ($invoice['amount_paid'] ?? 0) / 100,
                ],
            );
        } else {
            BillingAudit::log(
                action: 'payment_succeeded',
                restaurantId: $restaurant->id,
                actorType: 'stripe',
                payload: [
                    'invoice_id' => $invoice['id'] ?? null,
                    'amount_paid' => ($invoice['amount_paid'] ?? 0) / 100,
                ],
            );
        }
    }

    private function applyPendingDowngrade(Restaurant $restaurant): void
    {
        if (! $restaurant->hasPendingDowngrade()) {
            return;
        }

        if (! $restaurant->isSubscriptionMode()) {
            Log::warning("Skipping pending downgrade for manual-mode restaurant {$restaurant->id}");
            $restaurant->clearPendingDowngrade();

            return;
        }

        $pendingPlan = $restaurant->pendingPlan;

        if (! $pendingPlan) {
            $restaurant->clearPendingDowngrade();

            return;
        }

        $oldPlan = $restaurant->plan;

        $cycle = $restaurant->pending_billing_cycle ?? 'monthly';
        $priceId = $cycle === 'yearly'
            ? $pendingPlan->stripe_yearly_price_id
            : $pendingPlan->stripe_monthly_price_id;
        $subscription = $restaurant->subscription('default');

        if ($subscription && $priceId) {
            try {
                $subscription->swap($priceId);

                // Sync the new billing period
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
                Log::error("Failed to apply pending downgrade for restaurant {$restaurant->id}: ".$e->getMessage());

                return; // Do NOT update local plan if Stripe swap failed
            }
        }

        $restaurant->assignPlan($pendingPlan);
        $restaurant->clearPendingDowngrade();

        BillingAudit::log(
            action: 'downgrade_applied',
            restaurantId: $restaurant->id,
            actorType: 'stripe',
            payload: [
                'old_plan' => $oldPlan?->name,
                'new_plan' => $pendingPlan->name,
                'billing_cycle' => $cycle,
                'source' => 'invoice.paid',
            ],
        );
    }

    public function handleInvoicePaymentFailed(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $stripeCustomerId = $invoice['customer'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $restaurant = Restaurant::query()->where('stripe_id', $stripeCustomerId)->first();

        if (! $restaurant) {
            return;
        }

        // First failure: active → grace_period (not immediate block).
        // Stripe will retry, and the restaurant keeps operating during grace.
        if ($restaurant->status === 'active') {
            $graceDays = BillingSetting::getInt('payment_grace_period_days', 7);

            $restaurant->transitionTo('grace_period', [
                'grace_period_ends_at' => now()->addDays($graceDays),
            ]);

            BillingAudit::log(
                action: 'payment_failed',
                restaurantId: $restaurant->id,
                actorType: 'stripe',
                payload: [
                    'invoice_id' => $invoice['id'] ?? null,
                    'attempt_count' => $invoice['attempt_count'] ?? null,
                    'grace_days' => $graceDays,
                    'grace_period_ends_at' => $restaurant->grace_period_ends_at->toIso8601String(),
                ],
            );
        }
    }

    public function handleCustomerSubscriptionUpdated(array $payload): void
    {
        parent::handleCustomerSubscriptionUpdated($payload);

        $subscription = $payload['data']['object'];
        $stripeCustomerId = $subscription['customer'] ?? null;
        $stripeStatus = $subscription['status'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $restaurant = Restaurant::query()->where('stripe_id', $stripeCustomerId)->first();

        if (! $restaurant) {
            return;
        }

        // Sync billing period from Stripe
        $this->syncBillingPeriod($restaurant, $subscription);

        // Safety net: if Stripe says past_due/unpaid but local is still active, start grace period
        if (in_array($stripeStatus, ['past_due', 'unpaid']) && $restaurant->status === 'active') {
            $graceDays = BillingSetting::getInt('payment_grace_period_days', 7);

            $restaurant->transitionTo('grace_period', [
                'grace_period_ends_at' => now()->addDays($graceDays),
            ]);

            BillingAudit::log(
                action: 'grace_period_started',
                restaurantId: $restaurant->id,
                actorType: 'stripe',
                payload: [
                    'grace_days' => $graceDays,
                    'grace_period_ends_at' => $restaurant->grace_period_ends_at->toIso8601String(),
                    'stripe_status' => $stripeStatus,
                    'source' => 'subscription_updated_safety_net',
                ],
            );
        }

        $stripePriceId = $subscription['items']['data'][0]['price']['id'] ?? null;

        if ($stripePriceId) {
            $plan = Plan::query()
                ->where('stripe_monthly_price_id', $stripePriceId)
                ->orWhere('stripe_yearly_price_id', $stripePriceId)
                ->first();

            if ($plan && $restaurant->plan_id !== $plan->id) {
                $oldPlan = $restaurant->plan;

                $restaurant->assignPlan($plan);

                BillingAudit::log(
                    action: 'plan_changed',
                    restaurantId: $restaurant->id,
                    actorType: 'stripe',
                    payload: [
                        'old_plan' => $oldPlan?->name,
                        'new_plan' => $plan->name,
                        'stripe_price_id' => $stripePriceId,
                    ],
                );
            }
        }
    }

    private function syncBillingPeriod(Restaurant $restaurant, array $stripeSubscription): void
    {
        $firstItem = $stripeSubscription['items']['data'][0] ?? null;

        if (! $firstItem) {
            Log::warning("syncBillingPeriod: no items found for restaurant {$restaurant->id}");

            return;
        }

        $periodStart = $firstItem['current_period_start'] ?? null;
        $periodEnd = $firstItem['current_period_end'] ?? null;

        if (! $periodStart || ! $periodEnd) {
            return;
        }

        $localSub = $restaurant->subscription('default');

        if (! $localSub) {
            return;
        }

        $localSub->update([
            'current_period_start' => Carbon::createFromTimestamp($periodStart),
            'current_period_end' => Carbon::createFromTimestamp($periodEnd),
        ]);
    }

    public function handleCustomerSubscriptionDeleted(array $payload): void
    {
        parent::handleCustomerSubscriptionDeleted($payload);

        $subscription = $payload['data']['object'];
        $stripeCustomerId = $subscription['customer'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $restaurant = Restaurant::query()->where('stripe_id', $stripeCustomerId)->first();

        if (! $restaurant) {
            return;
        }

        $restaurant->transitionTo('suspended');

        BillingAudit::log(
            action: 'suspended',
            restaurantId: $restaurant->id,
            actorType: 'stripe',
            payload: ['reason' => 'subscription_deleted'],
        );
    }
}
