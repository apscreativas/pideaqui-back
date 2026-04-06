<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Billing cron jobs — run daily
Schedule::command('billing:check-grace')->daily()->at('06:00');
Schedule::command('billing:check-canceled')->daily()->at('06:05');
Schedule::command('billing:send-reminders')->daily()->at('08:00');
Schedule::command('billing:reconcile')->daily()->at('03:00');
Schedule::command('billing:apply-pending-downgrades')->hourly();
