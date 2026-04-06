<?php

namespace Tests\Feature;

use App\Models\BillingAudit;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Services\LimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    // ─── Plan Model ─────────────────────────────────────────────────────────

    public function test_grace_plan_can_be_retrieved(): void
    {
        Plan::factory()->grace()->create();

        $plan = Plan::gracePlan();

        $this->assertNotNull($plan);
        $this->assertTrue($plan->is_default_grace);
        $this->assertEquals('gracia', $plan->slug);
    }

    public function test_purchasable_plans_exclude_grace_and_inactive(): void
    {
        Plan::factory()->grace()->create();
        Plan::factory()->basico()->create();
        Plan::factory()->pro()->create();
        Plan::factory()->create(['is_active' => false, 'slug' => 'inactive']);

        $purchasable = Plan::purchasable();

        $this->assertCount(2, $purchasable);
        $this->assertEquals(['basico', 'pro'], $purchasable->pluck('slug')->all());
    }

    public function test_plans_are_ordered_by_sort_order(): void
    {
        Plan::factory()->enterprise()->create();
        Plan::factory()->basico()->create();
        Plan::factory()->pro()->create();

        $plans = Plan::purchasable();

        $this->assertEquals(['basico', 'pro', 'enterprise'], $plans->pluck('slug')->all());
    }

    // ─── BillingSetting Model ───────────────────────────────────────────────

    public function test_billing_setting_get_and_set(): void
    {
        BillingSetting::set('test_key', '42');

        $this->assertEquals('42', BillingSetting::get('test_key'));
        $this->assertEquals(42, BillingSetting::getInt('test_key'));
    }

    public function test_billing_setting_returns_default_when_missing(): void
    {
        $this->assertEquals('default', BillingSetting::get('missing_key', 'default'));
        $this->assertEquals(7, BillingSetting::getInt('missing_key', 7));
    }

    public function test_billing_setting_upserts(): void
    {
        BillingSetting::set('key', 'value1');
        BillingSetting::set('key', 'value2');

        $this->assertEquals('value2', BillingSetting::get('key'));
        $this->assertEquals(1, BillingSetting::query()->where('key', 'key')->count());
    }

    // ─── BillingAudit Model ─────────────────────────────────────────────────

    public function test_billing_audit_log_creates_record(): void
    {
        $restaurant = Restaurant::factory()->create();

        $audit = BillingAudit::log(
            action: 'restaurant_created',
            restaurantId: $restaurant->id,
            actorType: 'super_admin',
            actorId: 1,
            payload: ['name' => $restaurant->name],
            ipAddress: '127.0.0.1',
        );

        $this->assertDatabaseHas('billing_audits', [
            'id' => $audit->id,
            'restaurant_id' => $restaurant->id,
            'actor_type' => 'super_admin',
            'action' => 'restaurant_created',
        ]);
    }

    public function test_billing_audit_belongs_to_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $audit = BillingAudit::log('test_action', $restaurant->id);

        $this->assertTrue($audit->restaurant->is($restaurant));
    }

    // ─── Restaurant Status Methods ──────────────────────────────────────────

    public function test_active_restaurant_can_receive_orders(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);

        $this->assertTrue($restaurant->canReceiveOrders());
    }

    public function test_past_due_restaurant_cannot_receive_orders(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'past_due']);

        $this->assertFalse($restaurant->canReceiveOrders());
    }

    public function test_grace_period_restaurant_can_receive_orders(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'grace_period']);

        $this->assertTrue($restaurant->canReceiveOrders());
    }

    public function test_suspended_restaurant_cannot_receive_orders(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'suspended']);

        $this->assertFalse($restaurant->canReceiveOrders());
    }

    public function test_incomplete_restaurant_cannot_receive_orders(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'incomplete']);

        $this->assertFalse($restaurant->canReceiveOrders());
    }

    public function test_disabled_restaurant_cannot_receive_orders(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'disabled']);

        $this->assertFalse($restaurant->canReceiveOrders());
    }

    public function test_canceled_restaurant_can_receive_orders_within_period(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'canceled',
            'subscription_ends_at' => now()->addDays(5),
        ]);

        $this->assertTrue($restaurant->canReceiveOrders());
    }

    public function test_canceled_restaurant_cannot_receive_orders_after_period(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'canceled',
            'subscription_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($restaurant->canReceiveOrders());
    }

    public function test_disabled_restaurant_cannot_access_panel(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'disabled']);

        $this->assertFalse($restaurant->canAccessPanel());
    }

    public function test_suspended_restaurant_can_access_panel(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'suspended']);

        $this->assertTrue($restaurant->canAccessPanel());
    }

    public function test_suspended_restaurant_must_show_billing(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'suspended']);

        $this->assertTrue($restaurant->mustShowBilling());
    }

    public function test_active_restaurant_does_not_show_billing(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);

        $this->assertFalse($restaurant->mustShowBilling());
    }

    // ─── Restaurant effective limits ────────────────────────────────────────

    public function test_effective_limits_come_from_plan_when_assigned(): void
    {
        $plan = Plan::factory()->pro()->create();
        $restaurant = Restaurant::factory()->subscription()->create(['plan_id' => $plan->id]);

        $this->assertEquals(1000, $restaurant->getEffectiveOrdersLimit());
        $this->assertEquals(3, $restaurant->getEffectiveMaxBranches());
    }

    public function test_effective_limits_fall_back_to_legacy_without_plan(): void
    {
        $restaurant = Restaurant::factory()->create([
            'plan_id' => null,
            'orders_limit' => 500,
            'max_branches' => 2,
        ]);

        $this->assertEquals(500, $restaurant->getEffectiveOrdersLimit());
        $this->assertEquals(2, $restaurant->getEffectiveMaxBranches());
    }

    // ─── LimitService with Plans ────────────────────────────────────────────

    public function test_limit_service_uses_plan_limits(): void
    {
        $plan = Plan::factory()->create(['orders_limit' => 10, 'max_branches' => 2]);
        $restaurant = Restaurant::factory()->subscription()->create(['plan_id' => $plan->id]);

        $service = app(LimitService::class);

        $this->assertEquals(10, $service->getOrdersLimit($restaurant));
        $this->assertEquals(2, $service->getMaxBranches($restaurant));
    }

    public function test_limit_service_uses_legacy_limits_without_plan(): void
    {
        $restaurant = Restaurant::factory()->create([
            'plan_id' => null,
            'orders_limit' => 300,
            'max_branches' => 1,
        ]);

        $service = app(LimitService::class);

        $this->assertEquals(300, $service->getOrdersLimit($restaurant));
        $this->assertEquals(1, $service->getMaxBranches($restaurant));
    }

    public function test_limit_service_plan_based_limit_reached(): void
    {
        $plan = Plan::factory()->create(['orders_limit' => 2]);
        $restaurant = Restaurant::factory()->subscription()->create(['plan_id' => $plan->id]);

        // Create 2 orders this month
        \App\Models\Order::factory()->count(2)->create([
            'restaurant_id' => $restaurant->id,
            'created_at' => now(),
        ]);

        $service = app(LimitService::class);

        $this->assertTrue($service->isOrderLimitReached($restaurant));
        $this->assertEquals('limit_reached', $service->limitReason($restaurant));
    }

    public function test_limit_service_plan_based_limit_not_reached(): void
    {
        $plan = Plan::factory()->create(['orders_limit' => 100]);
        $restaurant = Restaurant::factory()->subscription()->create(['plan_id' => $plan->id]);

        $service = app(LimitService::class);

        $this->assertFalse($service->isOrderLimitReached($restaurant));
        $this->assertNull($service->limitReason($restaurant));
    }

    public function test_limit_service_legacy_still_works(): void
    {
        $restaurant = Restaurant::factory()->create([
            'plan_id' => null,
            'orders_limit' => 2,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
        ]);

        \App\Models\Order::factory()->count(2)->create([
            'restaurant_id' => $restaurant->id,
            'created_at' => now(),
        ]);

        $service = app(LimitService::class);

        $this->assertTrue($service->isOrderLimitReached($restaurant));
        $this->assertEquals('limit_reached', $service->limitReason($restaurant));
    }

    public function test_limit_service_current_period_for_plan(): void
    {
        $plan = Plan::factory()->create();
        $restaurant = Restaurant::factory()->subscription()->create(['plan_id' => $plan->id]);

        $service = app(LimitService::class);
        $period = $service->getCurrentPeriod($restaurant);

        $this->assertNotNull($period);
        $this->assertTrue($period['start']->isSameDay(now()->startOfMonth()));
        $this->assertTrue($period['end']->isSameDay(now()->endOfMonth()));
    }

    public function test_limit_service_current_period_for_legacy(): void
    {
        $start = now()->subDays(10);
        $end = now()->addDays(10);

        $restaurant = Restaurant::factory()->create([
            'plan_id' => null,
            'orders_limit_start' => $start,
            'orders_limit_end' => $end,
        ]);

        $service = app(LimitService::class);
        $period = $service->getCurrentPeriod($restaurant);

        $this->assertNotNull($period);
        $this->assertTrue($period['start']->isSameDay($start));
        $this->assertTrue($period['end']->isSameDay($end));
    }

    // ─── API Middleware ─────────────────────────────────────────────────────

    public function test_api_rejects_suspended_restaurant(): void
    {
        Restaurant::factory()->create([
            'access_token' => 'suspended-token',
            'is_active' => true,
            'status' => 'suspended',
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'suspended-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_api_allows_active_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create([
            'access_token' => 'active-token',
            'is_active' => true,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'active-token',
        ]);

        $response->assertStatus(200);
    }

    public function test_api_allows_grace_period_restaurant(): void
    {
        Restaurant::factory()->create([
            'access_token' => 'grace-token',
            'is_active' => true,
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'grace-token',
        ]);

        $response->assertStatus(200);
    }

    // ─── Security: past_due blocks API ──────────────────────────────────

    public function test_api_rejects_past_due_restaurant(): void
    {
        Restaurant::factory()->create([
            'access_token' => 'pastdue-token',
            'is_active' => true,
            'status' => 'past_due',
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'pastdue-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_api_rejects_incomplete_restaurant(): void
    {
        Restaurant::factory()->create([
            'access_token' => 'incomplete-token',
            'is_active' => false,
            'status' => 'incomplete',
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'incomplete-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_api_allows_canceled_restaurant_within_period(): void
    {
        Restaurant::factory()->create([
            'access_token' => 'canceled-active-token',
            'is_active' => true,
            'status' => 'canceled',
            'subscription_ends_at' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'canceled-active-token',
        ]);

        $response->assertStatus(200);
    }

    public function test_api_rejects_canceled_restaurant_past_period(): void
    {
        Restaurant::factory()->create([
            'access_token' => 'canceled-expired-token',
            'is_active' => true,
            'status' => 'canceled',
            'subscription_ends_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'canceled-expired-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_api_rejects_disabled_restaurant(): void
    {
        Restaurant::factory()->create([
            'access_token' => 'disabled-token',
            'is_active' => false,
            'status' => 'disabled',
        ]);

        $response = $this->getJson('/api/restaurant', [
            'X-Restaurant-Token' => 'disabled-token',
        ]);

        $response->assertStatus(401);
    }
}
