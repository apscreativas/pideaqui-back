<?php

namespace App\Console\Commands;

use App\Models\BillingAudit;
use App\Models\BillingSetting;
use App\Models\Restaurant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileBillingCommand extends Command
{
    protected $signature = 'billing:reconcile';

    protected $description = 'Compare local billing status with Stripe and fix discrepancies';

    public function handle(): int
    {
        $restaurants = Restaurant::query()
            ->whereNotNull('stripe_id')
            ->where('status', '!=', 'disabled')
            ->get();

        if ($restaurants->isEmpty()) {
            $this->info('No restaurants with Stripe accounts to reconcile.');

            return self::SUCCESS;
        }

        $discrepancies = 0;

        foreach ($restaurants as $restaurant) {
            try {
                $subscription = $restaurant->subscription('default');

                if (! $subscription) {
                    continue;
                }

                $stripeSubscription = $subscription->asStripeSubscription();
                $stripeStatus = $stripeSubscription->status;

                $expectedLocalStatus = $this->mapStripeStatus($stripeStatus, $restaurant);

                if ($expectedLocalStatus && $restaurant->status !== $expectedLocalStatus) {
                    $oldStatus = $restaurant->status;

                    $extra = [];

                    // If transitioning to grace_period, set grace_period_ends_at
                    if ($expectedLocalStatus === 'grace_period' && ! $restaurant->grace_period_ends_at) {
                        $graceDays = BillingSetting::getInt('payment_grace_period_days', 7);
                        $extra['grace_period_ends_at'] = now()->addDays($graceDays);
                    }

                    $restaurant->transitionTo($expectedLocalStatus, $extra);

                    BillingAudit::log(
                        action: 'reconciled',
                        restaurantId: $restaurant->id,
                        actorType: 'system',
                        payload: [
                            'old_status' => $oldStatus,
                            'new_status' => $expectedLocalStatus,
                            'stripe_status' => $stripeStatus,
                        ],
                    );

                    $this->warn("  [{$restaurant->id}] {$restaurant->name}: {$oldStatus} → {$expectedLocalStatus} (stripe: {$stripeStatus})");
                    $discrepancies++;
                }
            } catch (\Exception $e) {
                Log::warning("billing:reconcile failed for restaurant {$restaurant->id}: {$e->getMessage()}");
                $this->error("  [{$restaurant->id}] {$restaurant->name}: Error — {$e->getMessage()}");
            }
        }

        $this->info("Reconciled: {$restaurants->count()} restaurant(s), {$discrepancies} discrepancies fixed.");

        return self::SUCCESS;
    }

    private function mapStripeStatus(string $stripeStatus, Restaurant $restaurant): ?string
    {
        return match ($stripeStatus) {
            'active' => 'active',
            // Stripe past_due = payment failed but retrying → give grace period (not immediate block)
            'past_due' => $restaurant->status === 'grace_period' ? null : 'grace_period',
            'unpaid' => 'grace_period',
            'canceled' => 'suspended',
            'incomplete', 'incomplete_expired' => 'incomplete',
            default => null,
        };
    }
}
