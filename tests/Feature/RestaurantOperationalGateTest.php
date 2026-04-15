<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\LimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantOperationalGateTest extends TestCase
{
    use RefreshDatabase;

    private function limits(): LimitService
    {
        return app(LimitService::class);
    }

    // ─── Subscription mode ───────────────────────────────────────────────────

    public function test_subscription_active_can_operate(): void
    {
        $r = Restaurant::factory()->subscription()->create(['status' => 'active']);
        $this->assertTrue($r->canOperate($this->limits()));
        $this->assertNull($r->operationalBlockReason($this->limits()));
    }

    public function test_subscription_grace_period_can_operate(): void
    {
        $r = Restaurant::factory()->subscription()->create([
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(5),
        ]);
        $this->assertTrue($r->canOperate($this->limits()));
    }

    public function test_subscription_canceled_with_future_end_can_operate(): void
    {
        $r = Restaurant::factory()->subscription()->create([
            'status' => 'canceled',
            'subscription_ends_at' => now()->addDays(10),
        ]);
        $this->assertTrue($r->canOperate($this->limits()));
    }

    public function test_subscription_canceled_with_past_end_is_blocked_even_if_cron_has_not_run(): void
    {
        // Defense in depth: if the `billing:check-canceled` cron hasn't run,
        // a canceled restaurant past its period must still be blocked.
        $r = Restaurant::factory()->subscription()->create([
            'status' => 'canceled',
            'subscription_ends_at' => now()->subDays(1),
        ]);
        $this->assertFalse($r->canOperate($this->limits()));
        $this->assertSame('subscription_expired', $r->operationalBlockReason($this->limits()));
    }

    public function test_subscription_canceled_without_end_date_does_not_trigger_expired_gate(): void
    {
        // Legacy / edge case: if subscription_ends_at is somehow null on a
        // canceled restaurant, we don't fabricate an expiry — leave it operational
        // (the canReceiveOrders API gate already requires a future end date).
        $r = Restaurant::factory()->subscription()->create([
            'status' => 'canceled',
            'subscription_ends_at' => null,
        ]);
        $this->assertTrue($r->canOperate($this->limits()));
    }

    public function test_subscription_suspended_is_blocked(): void
    {
        $r = Restaurant::factory()->subscription()->create(['status' => 'suspended', 'is_active' => false]);
        $this->assertFalse($r->canOperate($this->limits()));
        $this->assertSame('suspended', $r->operationalBlockReason($this->limits()));
    }

    public function test_subscription_past_due_is_blocked(): void
    {
        $r = Restaurant::factory()->subscription()->create(['status' => 'past_due']);
        $this->assertFalse($r->canOperate($this->limits()));
        $this->assertSame('past_due', $r->operationalBlockReason($this->limits()));
    }

    public function test_subscription_disabled_is_blocked(): void
    {
        $r = Restaurant::factory()->subscription()->create(['status' => 'disabled', 'is_active' => false]);
        $this->assertFalse($r->canOperate($this->limits()));
        $this->assertSame('disabled', $r->operationalBlockReason($this->limits()));
    }

    public function test_subscription_incomplete_is_blocked(): void
    {
        $r = Restaurant::factory()->subscription()->create(['status' => 'incomplete']);
        $this->assertFalse($r->canOperate($this->limits()));
        $this->assertSame('incomplete', $r->operationalBlockReason($this->limits()));
    }

    // ─── Manual mode ─────────────────────────────────────────────────────────

    public function test_manual_active_with_current_period_can_operate(): void
    {
        $r = Restaurant::factory()->create([
            'status' => 'active',
            'orders_limit_start' => now()->subDays(5),
            'orders_limit_end' => now()->addDays(25),
            'orders_limit' => 100,
        ]);
        $this->assertTrue($r->canOperate($this->limits()));
    }

    public function test_manual_period_expired_is_blocked(): void
    {
        $r = Restaurant::factory()->create([
            'status' => 'active',
            'orders_limit_start' => now()->subDays(30),
            'orders_limit_end' => now()->subDays(1),
            'orders_limit' => 100,
        ]);
        $this->assertFalse($r->canOperate($this->limits()));
        $this->assertSame('period_expired', $r->operationalBlockReason($this->limits()));
    }

    public function test_manual_period_not_started_is_blocked(): void
    {
        $r = Restaurant::factory()->create([
            'status' => 'active',
            'orders_limit_start' => now()->addDays(5),
            'orders_limit_end' => now()->addDays(35),
            'orders_limit' => 100,
        ]);
        $this->assertFalse($r->canOperate($this->limits()));
        $this->assertSame('period_not_started', $r->operationalBlockReason($this->limits()));
    }

    public function test_manual_reaching_limit_does_not_block_operational_gate(): void
    {
        // Critical: reaching orders_limit blocks orders (via LimitService) but
        // does NOT block POS or the operational gate. POS stays open at limit.
        $r = Restaurant::factory()->create([
            'status' => 'active',
            'orders_limit_start' => now()->subDays(5),
            'orders_limit_end' => now()->addDays(25),
            'orders_limit' => 10,
        ]);

        // Create 10 orders to reach the limit.
        $branch = \App\Models\Branch::factory()->create(['restaurant_id' => $r->id]);
        $customer = \App\Models\Customer::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            \App\Models\Order::factory()->create([
                'restaurant_id' => $r->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
            ]);
        }

        $this->assertTrue($this->limits()->isOrderLimitReached($r), 'Limit should be reached');
        $this->assertTrue($r->canOperate($this->limits()), 'Operational gate must stay open at limit');
        $this->assertNull($r->operationalBlockReason($this->limits()));
    }

    public function test_manual_disabled_status_wins_over_period(): void
    {
        $r = Restaurant::factory()->create([
            'status' => 'disabled',
            'is_active' => false,
            'orders_limit_start' => now()->subDays(5),
            'orders_limit_end' => now()->addDays(25),
        ]);
        $this->assertSame('disabled', $r->operationalBlockReason($this->limits()));
    }
}
