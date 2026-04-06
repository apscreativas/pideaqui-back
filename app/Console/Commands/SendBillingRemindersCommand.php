<?php

namespace App\Console\Commands;

use App\Models\BillingSetting;
use App\Models\Restaurant;
use App\Notifications\GraceExpiringNotification;
use Illuminate\Console\Command;

class SendBillingRemindersCommand extends Command
{
    protected $signature = 'billing:send-reminders';

    protected $description = 'Send email reminders to restaurants whose grace period is about to expire';

    public function handle(): int
    {
        $reminderDays = array_map(
            'intval',
            array_filter(explode(',', BillingSetting::get('reminder_days_before_expiry', '3,1')))
        );

        if (empty($reminderDays)) {
            $this->info('No reminder days configured.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($reminderDays as $days) {
            $targetDate = now()->addDays($days)->startOfDay();

            $restaurants = Restaurant::query()
                ->where('status', 'grace_period')
                ->whereNotNull('grace_period_ends_at')
                ->whereDate('grace_period_ends_at', $targetDate)
                ->get();

            foreach ($restaurants as $restaurant) {
                $restaurant->notify(new GraceExpiringNotification($restaurant, $days));
                $this->info("  [{$restaurant->id}] {$restaurant->name} — reminder: {$days} days left");
                $sent++;
            }
        }

        $this->info("Sent: {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
