<?php

namespace App\Console\Commands;

use App\Models\BillingAudit;
use App\Models\Restaurant;
use Illuminate\Console\Command;

class CheckGracePeriodCommand extends Command
{
    protected $signature = 'billing:check-grace';

    protected $description = 'Suspend restaurants whose grace period has expired';

    public function handle(): int
    {
        $restaurants = Restaurant::query()
            ->where('status', 'grace_period')
            ->where('grace_period_ends_at', '<', now())
            ->get();

        if ($restaurants->isEmpty()) {
            $this->info('No expired grace periods found.');

            return self::SUCCESS;
        }

        foreach ($restaurants as $restaurant) {
            $restaurant->transitionTo('suspended');

            BillingAudit::log(
                action: 'suspended',
                restaurantId: $restaurant->id,
                actorType: 'system',
                payload: ['reason' => 'grace_period_expired', 'expired_at' => $restaurant->grace_period_ends_at?->toIso8601String()],
            );

            $this->info("  [{$restaurant->id}] {$restaurant->name} → suspended (grace expired)");
        }

        $this->info("Suspended: {$restaurants->count()} restaurant(s).");

        return self::SUCCESS;
    }
}
