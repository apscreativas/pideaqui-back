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

        // Planes comerciales
        Plan::query()->updateOrCreate(['slug' => 'basico'], [
            'name' => 'Básico',
            'description' => 'Ideal para empezar tu negocio digital',
            'orders_limit' => 300,
            'max_branches' => 1,
            'monthly_price' => 499.00,
            'yearly_price' => 4990.00,
            'is_default_grace' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::query()->updateOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'description' => 'Para restaurantes en crecimiento',
            'orders_limit' => 1000,
            'max_branches' => 3,
            'monthly_price' => 999.00,
            'yearly_price' => 9990.00,
            'is_default_grace' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Plan::query()->updateOrCreate(['slug' => 'enterprise'], [
            'name' => 'Enterprise',
            'description' => 'Para cadenas y alto volumen',
            'orders_limit' => 5000,
            'max_branches' => 10,
            'monthly_price' => 2499.00,
            'yearly_price' => 24990.00,
            'is_default_grace' => false,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Configuración de billing
        BillingSetting::set('initial_grace_period_days', '14');
        BillingSetting::set('payment_grace_period_days', '7');
        BillingSetting::set('reminder_days_before_expiry', '3,1');
    }
}
