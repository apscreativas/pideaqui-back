<?php

namespace App\Console\Commands;

use App\Models\BillingAudit;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ApplyPendingDowngradesCommand extends Command
{
    protected $signature = 'billing:apply-pending-downgrades';

    protected $description = 'Apply scheduled plan downgrades whose effective date has passed (safety net for missed webhooks)';

    public function handle(): int
    {
        $restaurants = Restaurant::query()
            ->whereNotNull('pending_plan_id')
            ->where('pending_plan_effective_at', '<=', now())
            ->with(['plan', 'pendingPlan'])
            ->get();

        if ($restaurants->isEmpty()) {
            $this->info('No pending downgrades to apply.');

            return self::SUCCESS;
        }

        foreach ($restaurants as $restaurant) {
            $this->applyDowngrade($restaurant);
        }

        $this->info("Applied {$restaurants->count()} pending downgrade(s).");

        return self::SUCCESS;
    }

    private function applyDowngrade(Restaurant $restaurant): void
    {
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
                $this->error("Restaurant {$restaurant->id}: {$e->getMessage()}");

                return;
            }
        }

        $restaurant->assignPlan($pendingPlan);
        $restaurant->clearPendingDowngrade();

        BillingAudit::log(
            action: 'downgrade_applied',
            restaurantId: $restaurant->id,
            actorType: 'system',
            payload: [
                'old_plan' => $oldPlan?->name,
                'new_plan' => $pendingPlan->name,
                'source' => 'billing:apply-pending-downgrades',
            ],
        );

        $this->line("Restaurant {$restaurant->id}: {$oldPlan?->name} → {$pendingPlan->name}");
    }
}
