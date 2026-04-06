<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;

class SyncPlansToStripeCommand extends Command
{
    protected $signature = 'billing:sync-stripe {--dry-run : Show what would happen without making changes}';

    protected $description = 'Create or update Stripe Products and Prices for local plans';

    public function handle(): int
    {
        $stripe = Cashier::stripe();

        $plans = Plan::query()
            ->where('is_default_grace', false)
            ->orderBy('sort_order')
            ->get();

        if ($plans->isEmpty()) {
            $this->error('No purchasable plans found. Run BillingSeeder first.');

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');

        foreach ($plans as $plan) {
            $this->info("─── {$plan->name} (slug: {$plan->slug}) ───");

            // 1. Product
            $this->syncProduct($stripe, $plan, $isDryRun);

            if (! $plan->stripe_product_id && ! $isDryRun) {
                continue;
            }

            // 2. Monthly Price
            $this->syncPrice($stripe, $plan, 'monthly', $isDryRun);

            // 3. Yearly Price
            $this->syncPrice($stripe, $plan, 'yearly', $isDryRun);

            $this->newLine();
        }

        if ($isDryRun) {
            $this->warn('Dry run completado. No se hicieron cambios.');
        } else {
            $this->info('Sincronización completada.');
        }

        return self::SUCCESS;
    }

    private function syncProduct($stripe, Plan $plan, bool $isDryRun): void
    {
        $metadata = [
            'plan_id' => $plan->id,
            'slug' => $plan->slug,
            'orders_limit' => $plan->orders_limit,
            'max_branches' => $plan->max_branches,
        ];

        if ($plan->stripe_product_id) {
            if (! $isDryRun) {
                try {
                    $stripe->products->update($plan->stripe_product_id, [
                        'name' => $plan->name,
                        'description' => $plan->description ?: $plan->name,
                        'metadata' => $metadata,
                    ]);
                    $this->line("  Product actualizado: {$plan->stripe_product_id}");
                } catch (\Exception $e) {
                    $this->error("  Error actualizando product: {$e->getMessage()}");
                }
            } else {
                $this->line("  Product: {$plan->stripe_product_id} (ya existe)");
            }
        } else {
            if ($isDryRun) {
                $this->line("  [DRY] Crearía Product: {$plan->name}");
            } else {
                try {
                    $product = $stripe->products->create([
                        'name' => $plan->name,
                        'description' => $plan->description ?: $plan->name,
                        'metadata' => $metadata,
                    ]);
                    $plan->update(['stripe_product_id' => $product->id]);
                    $this->info("  Product creado: {$product->id}");
                } catch (\Exception $e) {
                    $this->error("  Error creando product: {$e->getMessage()}");
                }
            }
        }
    }

    private function syncPrice($stripe, Plan $plan, string $interval, bool $isDryRun): void
    {
        $isMonthly = $interval === 'monthly';
        $localAmount = (int) round(($isMonthly ? $plan->monthly_price : $plan->yearly_price) * 100);
        $priceIdField = $isMonthly ? 'stripe_monthly_price_id' : 'stripe_yearly_price_id';
        $currentPriceId = $plan->$priceIdField;
        $label = $isMonthly ? 'mensual' : 'anual';
        $stripeInterval = $isMonthly ? 'month' : 'year';

        // Check if existing price matches the local amount
        if ($currentPriceId) {
            try {
                $existingPrice = $stripe->prices->retrieve($currentPriceId);
                $stripeAmount = $existingPrice->unit_amount;

                if ($stripeAmount === $localAmount) {
                    $this->line("  Price {$label}: {$currentPriceId} — \${$plan->{$isMonthly ? 'monthly_price' : 'yearly_price'}} MXN (correcto)");

                    return;
                }

                // Price changed — archive old, create new
                $this->warn("  Price {$label}: monto cambió ($".(int) ($stripeAmount / 100).' → $'.(int) ($localAmount / 100).')');

                if ($isDryRun) {
                    $this->line("  [DRY] Archivaría {$currentPriceId} y crearía nuevo Price {$label}");

                    return;
                }

                // Archive old price
                $stripe->prices->update($currentPriceId, ['active' => false]);
                $this->line("  Price anterior archivado: {$currentPriceId}");

            } catch (\Exception $e) {
                $this->warn("  No se pudo verificar price {$label}: {$e->getMessage()}. Creando nuevo.");
            }
        }

        // Create new price
        if ($isDryRun) {
            $localPrice = $isMonthly ? $plan->monthly_price : $plan->yearly_price;
            $this->line("  [DRY] Crearía Price {$label}: \${$localPrice} MXN/{$stripeInterval}");

            return;
        }

        try {
            $newPrice = $stripe->prices->create([
                'product' => $plan->stripe_product_id,
                'unit_amount' => $localAmount,
                'currency' => config('cashier.currency', 'mxn'),
                'recurring' => ['interval' => $stripeInterval],
                'metadata' => [
                    'plan_id' => $plan->id,
                    'billing_cycle' => $interval,
                ],
            ]);
            $plan->update([$priceIdField => $newPrice->id]);
            $this->info("  Price {$label} creado: {$newPrice->id} — $".(int) ($localAmount / 100)." MXN/{$stripeInterval}");
        } catch (\Exception $e) {
            $this->error("  Error creando price {$label}: {$e->getMessage()}");
        }
    }
}
