<?php

namespace Database\Seeders;

use App\Models\BillingSetting;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        // Plan de gracia (no comprable, asignado a restaurantes nuevos)
        Plan::query()->updateOrCreate(['slug' => 'gracia'], [
            'name' => 'Gracia',
            'description' => 'Plan temporal para restaurantes nuevos',
            'orders_limit' => 50,
            'max_branches' => 1,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'is_default_grace' => true,
            'is_active' => false,
            'sort_order' => 0,
        ]);

        

        // Configuración de billing
        BillingSetting::set('initial_grace_period_days', '14');
        BillingSetting::set('payment_grace_period_days', '7');
        BillingSetting::set('reminder_days_before_expiry', '3,1');
    }
}
