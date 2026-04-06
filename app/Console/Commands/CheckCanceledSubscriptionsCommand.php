<?php

namespace App\Console\Commands;

use App\Models\BillingAudit;
use App\Models\Restaurant;
use Illuminate\Console\Command;

class CheckCanceledSubscriptionsCommand extends Command
{
    protected $signature = 'billing:check-canceled';

    protected $description = 'Suspend restaurants whose canceled subscription period has ended';

    public function handle(): int
    {
        $restaurants = Restaurant::query()
            ->where('status', 'canceled')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        if ($restaurants->isEmpty()) {
            $this->info('No expired canceled subscriptions found.');

            return self::SUCCESS;
        }

        foreach ($restaurants as $restaurant) {
            $restaurant->transitionTo('suspended');

            BillingAudit::log(
                action: 'suspended',
                restaurantId: $restaurant->id,
                actorType: 'system',
                payload: ['reason' => 'canceled_period_ended', 'ended_at' => $restaurant->subscription_ends_at?->toIso8601String()],
            );

            $this->info("  [{$restaurant->id}] {$restaurant->name} → suspended (canceled period ended)");
        }

        $this->info("Suspended: {$restaurants->count()} restaurant(s).");

        return self::SUCCESS;
    }
}
