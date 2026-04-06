<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DowngradeTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscribedRestaurant(Plan $plan): array
    {
        $restaurant = Restaurant::factory()->subscription()->create([
            'plan_id' => $plan->id,
            'orders_limit' => $plan->orders_limit,
            'max_branches' => $plan->max_branches,
            'status' => 'active',
        ]);

        // Create a Cashier subscription record
        $restaurant->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_fake_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id ?? 'price_fake',
            'quantity' => 1,
            'current_period_start' => now()->subDays(6),
            'current_period_end' => now()->addDays(24),
        ]);

        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── Downgrade is detected and scheduled ────────────────────────────────

    public function test_downgrade_is_scheduled_not_immediate(): void
    {
        $pro = Plan::factory()->pro()->create();
        $basico = Plan::factory()->basico()->create();

        [$user, $restaurant] = $this->createSubscribedRestaurant($pro);

        $response = $this->actingAs($user)->put(route('settings.subscription.swap'), [
            'plan_id' => $basico->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $restaurant->refresh();

        // Plan stays Pro
        $this->assertEquals($pro->id, $restaurant->plan_id);
        // Pending is Básico
        $this->assertEquals($basico->id, $restaurant->pending_plan_id);
        $this->assertNotNull($restaurant->pending_plan_effective_at);
    }

    public function test_downgrade_detected_by_orders_limit(): void
    {
        $current = Plan::factory()->create(['orders_limit' => 1000, 'max_branches' => 3, 'slug' => 'current']);
        $lower = Plan::factory()->create(['orders_limit' => 300, 'max_branches' => 3, 'slug' => 'lower']);

        [$user, $restaurant] = $this->createSubscribedRestaurant($current);

        $this->actingAs($user)->put(route('settings.subscription.swap'), [
            'plan_id' => $lower->id,
            'billing_cycle' => 'monthly',
        ]);

        $restaurant->refresh();
        $this->assertEquals($current->id, $restaurant->plan_id);
        $this->assertEquals($lower->id, $restaurant->pending_plan_id);
    }

    public function test_downgrade_detected_by_max_branches(): void
    {
        $current = Plan::factory()->create(['orders_limit' => 300, 'max_branches' => 5, 'slug' => 'current']);
        $lower = Plan::factory()->create(['orders_limit' => 300, 'max_branches' => 1, 'slug' => 'lower']);

        [$user, $restaurant] = $this->createSubscribedRestaurant($current);

        $this->actingAs($user)->put(route('settings.subscription.swap'), [
            'plan_id' => $lower->id,
            'billing_cycle' => 'monthly',
        ]);

        $restaurant->refresh();
        $this->assertEquals($current->id, $restaurant->plan_id);
        $this->assertEquals($lower->id, $restaurant->pending_plan_id);
    }

    public function test_downgrade_audit_log_created(): void
    {
        $pro = Plan::factory()->pro()->create();
        $basico = Plan::factory()->basico()->create();

        [$user, $restaurant] = $this->createSubscribedRestaurant($pro);

        $this->actingAs($user)->put(route('settings.subscription.swap'), [
            'plan_id' => $basico->id,
            'billing_cycle' => 'monthly',
        ]);

        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'downgrade_scheduled',
            'actor_type' => 'restaurant_admin',
        ]);
    }

    // ─── Cancel pending downgrade ───────────────────────────────────────────

    public function test_cancel_pending_downgrade(): void
    {
        $pro = Plan::factory()->pro()->create();
        $basico = Plan::factory()->basico()->create();

        $restaurant = Restaurant::factory()->subscription()->create([
            'plan_id' => $pro->id,
            'pending_plan_id' => $basico->id,
            'pending_plan_effective_at' => now()->addDays(20),
        ]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($user)->delete(route('settings.subscription.cancel-pending'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $restaurant->refresh();
        $this->assertNull($restaurant->pending_plan_id);
        $this->assertNull($restaurant->pending_plan_effective_at);
    }

    public function test_cancel_downgrade_audit_log(): void
    {
        $pro = Plan::factory()->pro()->create();
        $basico = Plan::factory()->basico()->create();

        $restaurant = Restaurant::factory()->subscription()->create([
            'plan_id' => $pro->id,
            'pending_plan_id' => $basico->id,
            'pending_plan_effective_at' => now()->addDays(20),
        ]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)->delete(route('settings.subscription.cancel-pending'));

        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'downgrade_canceled',
        ]);
    }

    public function test_cancel_without_pending_returns_error(): void
    {
        $pro = Plan::factory()->pro()->create();
        $restaurant = Restaurant::factory()->subscription()->create(['plan_id' => $pro->id]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($user)->delete(route('settings.subscription.cancel-pending'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ─── Upgrade clears pending downgrade ───────────────────────────────────

    public function test_upgrade_clears_pending_downgrade(): void
    {
        $basico = Plan::factory()->basico()->create();
        $pro = Plan::factory()->pro()->create();
        $enterprise = Plan::factory()->enterprise()->create();

        // Restaurant on Pro with pending downgrade to Básico
        [$user, $restaurant] = $this->createSubscribedRestaurant($pro);
        $restaurant->update([
            'pending_plan_id' => $basico->id,
            'pending_plan_effective_at' => now()->addDays(20),
        ]);

        // This would be an upgrade (Enterprise has higher limits than Pro)
        // But since it calls Stripe API, we can't test the full swap.
        // We test the model logic directly instead.
        $this->assertTrue($restaurant->hasPendingDowngrade());

        $restaurant->clearPendingDowngrade();
        $restaurant->refresh();

        $this->assertFalse($restaurant->hasPendingDowngrade());
        $this->assertNull($restaurant->pending_plan_id);
    }

    // ─── Pending downgrade model methods ────────────────────────────────────

    public function test_has_pending_downgrade(): void
    {
        $basico = Plan::factory()->basico()->create();
        $restaurant = Restaurant::factory()->create([
            'pending_plan_id' => $basico->id,
            'pending_plan_effective_at' => now()->addDays(20),
        ]);

        $this->assertTrue($restaurant->hasPendingDowngrade());
    }

    public function test_no_pending_downgrade(): void
    {
        $restaurant = Restaurant::factory()->create([
            'pending_plan_id' => null,
            'pending_plan_effective_at' => null,
        ]);

        $this->assertFalse($restaurant->hasPendingDowngrade());
    }

    public function test_pending_plan_relationship(): void
    {
        $basico = Plan::factory()->basico()->create();
        $restaurant = Restaurant::factory()->subscription()->create(['pending_plan_id' => $basico->id]);

        $this->assertTrue($restaurant->pendingPlan->is($basico));
    }

    // ─── Cron: apply pending downgrades ─────────────────────────────────────

    public function test_cron_does_nothing_without_pending(): void
    {
        Restaurant::factory()->create();

        $this->artisan('billing:apply-pending-downgrades')
            ->expectsOutput('No pending downgrades to apply.')
            ->assertSuccessful();
    }

    public function test_cron_does_not_apply_future_downgrades(): void
    {
        $basico = Plan::factory()->basico()->create();
        Restaurant::factory()->create([
            'pending_plan_id' => $basico->id,
            'pending_plan_effective_at' => now()->addDays(20),
        ]);

        $this->artisan('billing:apply-pending-downgrades')
            ->expectsOutput('No pending downgrades to apply.')
            ->assertSuccessful();
    }

    // ─── Subscription page shows pending info ───────────────────────────────

    public function test_subscription_page_includes_pending_plan(): void
    {
        $pro = Plan::factory()->pro()->create();
        $basico = Plan::factory()->basico()->create();

        [$user, $restaurant] = $this->createSubscribedRestaurant($pro);
        $restaurant->update([
            'pending_plan_id' => $basico->id,
            'pending_plan_effective_at' => now()->addDays(20),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.subscription'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Subscription')
            ->where('restaurant.pending_plan.id', $basico->id)
            ->where('restaurant.pending_plan.name', $basico->name)
            ->has('restaurant.pending_plan_effective_at')
        );
    }

    public function test_subscription_page_without_pending(): void
    {
        $pro = Plan::factory()->pro()->create();
        [$user] = $this->createSubscribedRestaurant($pro);

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.subscription'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Subscription')
            ->where('restaurant.pending_plan', null)
            ->where('restaurant.pending_plan_effective_at', null)
        );
    }
}
