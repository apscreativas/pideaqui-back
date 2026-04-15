<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Services\LimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LimitService
    {
        return app(LimitService::class);
    }

    private function seedOrders(Restaurant $restaurant, int $count): void
    {
        if ($count <= 0) {
            return;
        }
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $customer = Customer::factory()->create();
        Order::factory()->count($count)->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
        ]);
    }

    // ─── Manual mode — limit thresholds ───────────────────────────────────────

    public function test_manual_mode_with_zero_used_is_below_limit(): void
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 10]);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals(0, $summary['used']);
        $this->assertEquals(10, $summary['limit']);
        $this->assertNull($summary['reason']);
        $this->assertTrue($summary['can_create']);
    }

    public function test_manual_mode_under_limit_can_create(): void
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 10]);
        $this->seedOrders($restaurant, 2);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals(2, $summary['used']);
        $this->assertEquals(10, $summary['limit']);
        $this->assertNull($summary['reason']);
        $this->assertTrue($summary['can_create']);
    }

    public function test_manual_mode_near_limit_still_allows(): void
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 10]);
        $this->seedOrders($restaurant, 9);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals(9, $summary['used']);
        $this->assertNull($summary['reason']);
        $this->assertTrue($summary['can_create']);
    }

    public function test_manual_mode_at_limit_blocks(): void
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 10]);
        $this->seedOrders($restaurant, 10);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals(10, $summary['used']);
        $this->assertEquals('limit_reached', $summary['reason']);
        $this->assertFalse($summary['can_create']);
    }

    public function test_manual_mode_over_limit_blocks(): void
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 10]);
        $this->seedOrders($restaurant, 11);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals(11, $summary['used']);
        $this->assertEquals('limit_reached', $summary['reason']);
        $this->assertFalse($summary['can_create']);
    }

    // ─── Manual mode — period boundaries ──────────────────────────────────────

    public function test_period_not_started_blocks_with_distinct_reason(): void
    {
        $restaurant = Restaurant::factory()->create([
            'orders_limit' => 10,
            'orders_limit_start' => now()->addDays(5),
            'orders_limit_end' => now()->addDays(35),
        ]);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals('period_not_started', $summary['reason']);
        $this->assertFalse($summary['can_create']);
        $this->assertNotNull($summary['period']['start']);
    }

    public function test_period_expired_blocks_with_distinct_reason_even_if_under_limit(): void
    {
        $restaurant = Restaurant::factory()->create([
            'orders_limit' => 10,
            'orders_limit_start' => now()->subDays(35),
            'orders_limit_end' => now()->subDays(5),
        ]);
        $this->seedOrders($restaurant, 2);

        $summary = $this->service()->summary($restaurant);

        // Reason must be expired — NOT limit_reached (this is the bug we fixed).
        $this->assertEquals('period_expired', $summary['reason']);
        $this->assertFalse($summary['can_create']);
    }

    // ─── Subscription mode ────────────────────────────────────────────────────

    public function test_subscription_mode_uses_plan_limit_not_legacy_column(): void
    {
        $plan = Plan::factory()->create(['orders_limit' => 50]);
        $restaurant = Restaurant::factory()->subscription()->create([
            'plan_id' => $plan->id,
            'orders_limit' => 999, // legacy column — must NOT be used
        ]);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals(50, $summary['limit']);
    }

    public function test_subscription_mode_under_plan_limit_can_create(): void
    {
        $plan = Plan::factory()->create(['orders_limit' => 50]);
        $restaurant = Restaurant::factory()->subscription()->create(['plan_id' => $plan->id]);

        $summary = $this->service()->summary($restaurant);

        $this->assertEquals(50, $summary['limit']);
        $this->assertNull($summary['reason']);
        $this->assertTrue($summary['can_create']);
    }
}
