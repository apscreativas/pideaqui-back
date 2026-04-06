<?php

namespace App\Console\Commands;

use App\Models\BillingAudit;
use App\Models\Plan;
use App\Models\Restaurant;
use Illuminate\Console\Command;

class BackfillPlansCommand extends Command
{
    protected $signature = 'billing:backfill-plans {--dry-run : Show what would happen without making changes}';

    protected $description = 'Assign the closest plan to existing restaurants based on their current limits';

    public function handle(): int
    {
        $plans = Plan::query()
            ->where('is_default_grace', false)
            ->where('is_active', true)
            ->orderBy('orders_limit')
            ->get();

        if ($plans->isEmpty()) {
            $this->error('No purchasable plans found. Run BillingSeeder first.');

            return self::FAILURE;
        }

        $restaurants = Restaurant::query()
            ->whereNull('plan_id')
            ->get();

        if ($restaurants->isEmpty()) {
            $this->info('All restaurants already have a plan assigned.');

            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $assigned = 0;
        $unmatched = [];

        foreach ($restaurants as $restaurant) {
            $bestPlan = $this->findBestPlan($restaurant, $plans);

            if (! $bestPlan) {
                $unmatched[] = $restaurant;
                $this->warn("  [{$restaurant->id}] {$restaurant->name} — orders_limit={$restaurant->orders_limit}, max_branches={$restaurant->max_branches} — NO PLAN FITS, assigning highest.");
                $bestPlan = $plans->last();
            }

            $statusMap = $restaurant->is_active ? 'active' : 'disabled';

            if ($isDryRun) {
                $this->line("  [DRY] [{$restaurant->id}] {$restaurant->name} → {$bestPlan->name} (status: {$statusMap})");
            } else {
                $oldOrdersLimit = $restaurant->orders_limit;
                $oldMaxBranches = $restaurant->max_branches;

                $restaurant->assignPlan($bestPlan);
                $restaurant->transitionToSubscription();
                $restaurant->transitionTo($statusMap);

                BillingAudit::log(
                    action: 'plan_changed',
                    restaurantId: $restaurant->id,
                    actorType: 'system',
                    payload: [
                        'plan_name' => $bestPlan->name,
                        'reason' => 'backfill',
                        'old_orders_limit' => $oldOrdersLimit,
                        'old_max_branches' => $oldMaxBranches,
                    ],
                );

                $this->info("  [{$restaurant->id}] {$restaurant->name} → {$bestPlan->name} (status: {$statusMap})");
            }

            $assigned++;
        }

        $this->newLine();
        $this->info("Processed: {$assigned} restaurants.");

        if (count($unmatched) > 0) {
            $this->warn(count($unmatched).' restaurant(s) exceeded the highest plan and were assigned to it. Review manually.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Plan>  $plans
     */
    private function findBestPlan(Restaurant $restaurant, $plans): ?Plan
    {
        foreach ($plans as $plan) {
            if (
                ($restaurant->orders_limit ?? 0) <= $plan->orders_limit
                && ($restaurant->max_branches ?? 1) <= $plan->max_branches
            ) {
                return $plan;
            }
        }

        return null;
    }
}
