<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCommandsTest extends TestCase
{
    use RefreshDatabase;

    // ─── billing:check-grace ────────────────────────────────────────────

    public function test_check_grace_suspends_expired_restaurants(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->subDay(),
        ]);

        $this->artisan('billing:check-grace')->assertSuccessful();

        $restaurant->refresh();
        $this->assertEquals('suspended', $restaurant->status);
        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'suspended',
        ]);
    }

    public function test_check_grace_ignores_active_grace_periods(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(5),
        ]);

        $this->artisan('billing:check-grace')->assertSuccessful();

        $restaurant->refresh();
        $this->assertEquals('grace_period', $restaurant->status);
    }

    // ─── billing:check-canceled ─────────────────────────────────────────

    public function test_check_canceled_suspends_expired_subscriptions(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'canceled',
            'subscription_ends_at' => now()->subDay(),
        ]);

        $this->artisan('billing:check-canceled')->assertSuccessful();

        $restaurant->refresh();
        $this->assertEquals('suspended', $restaurant->status);
        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'suspended',
        ]);
    }

    public function test_check_canceled_ignores_active_subscriptions(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'canceled',
            'subscription_ends_at' => now()->addDays(10),
        ]);

        $this->artisan('billing:check-canceled')->assertSuccessful();

        $restaurant->refresh();
        $this->assertEquals('canceled', $restaurant->status);
    }

    // ─── billing:backfill-plans ─────────────────────────────────────────

    public function test_backfill_assigns_plans_to_restaurants(): void
    {
        Plan::factory()->basico()->create();
        Plan::factory()->pro()->create();

        $small = Restaurant::factory()->create([
            'plan_id' => null,
            'orders_limit' => 200,
            'max_branches' => 1,
        ]);

        $medium = Restaurant::factory()->create([
            'plan_id' => null,
            'orders_limit' => 800,
            'max_branches' => 2,
        ]);

        $this->artisan('billing:backfill-plans')->assertSuccessful();

        $small->refresh();
        $medium->refresh();

        $this->assertEquals('basico', $small->plan->slug);
        $this->assertEquals('pro', $medium->plan->slug);
    }

    public function test_backfill_skips_restaurants_with_plan(): void
    {
        $plan = Plan::factory()->pro()->create();
        $restaurant = Restaurant::factory()->create(['plan_id' => $plan->id]);

        $this->artisan('billing:backfill-plans')->assertSuccessful();

        $restaurant->refresh();
        $this->assertEquals($plan->id, $restaurant->plan_id);
    }

    public function test_backfill_dry_run_does_not_modify(): void
    {
        Plan::factory()->basico()->create();
        $restaurant = Restaurant::factory()->create(['plan_id' => null]);

        $this->artisan('billing:backfill-plans --dry-run')->assertSuccessful();

        $restaurant->refresh();
        $this->assertNull($restaurant->plan_id);
    }

    // ─── SuperAdmin Plan CRUD ───────────────────────────────────────────

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::factory()->create();
    }

    public function test_superadmin_can_view_plans(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Plan::factory()->basico()->create();

        $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.plans.index'))
            ->assertOk();
    }

    public function test_superadmin_can_create_plan(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.plans.store'), [
                'name' => 'Test Plan',
                'slug' => 'test-plan',
                'description' => 'A test plan',
                'orders_limit' => 100,
                'max_branches' => 2,
                'monthly_price' => 299.00,
                'yearly_price' => 2990.00,
                'sort_order' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', ['slug' => 'test-plan']);
    }

    public function test_superadmin_can_toggle_plan(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $plan = Plan::factory()->create(['is_active' => true]);

        $this->actingAs($superAdmin, 'superadmin')
            ->patch(route('super.plans.toggle', $plan))
            ->assertRedirect();

        $plan->refresh();
        $this->assertFalse($plan->is_active);
    }

    public function test_superadmin_cannot_toggle_grace_plan(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $plan = Plan::factory()->grace()->create();

        $this->actingAs($superAdmin, 'superadmin')
            ->patch(route('super.plans.toggle', $plan))
            ->assertRedirect();

        $plan->refresh();
        $this->assertFalse($plan->is_active); // grace plan stays inactive
    }

    // ─── SuperAdmin Billing Settings ────────────────────────────────────

    public function test_superadmin_can_view_billing_settings(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.billing-settings'))
            ->assertOk();
    }

    public function test_superadmin_can_update_billing_settings(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'superadmin')
            ->put(route('super.billing-settings.update'), [
                'initial_grace_period_days' => 30,
                'payment_grace_period_days' => 14,
                'reminder_days_before_expiry' => '7,3,1',
            ])
            ->assertRedirect();

        $this->assertEquals('30', BillingSetting::get('initial_grace_period_days'));
        $this->assertEquals('14', BillingSetting::get('payment_grace_period_days'));
    }

    // ─── SuperAdmin Restaurant Plan Management ──────────────────────────

    public function test_superadmin_can_change_restaurant_plan(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $plan = Plan::factory()->pro()->create();
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($superAdmin, 'superadmin')
            ->put(route('super.restaurants.update-plan', $restaurant), [
                'plan_id' => $plan->id,
            ])
            ->assertRedirect();

        $restaurant->refresh();
        $this->assertEquals($plan->id, $restaurant->plan_id);
        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'plan_changed',
        ]);
    }

    public function test_superadmin_can_extend_grace(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create(['status' => 'suspended']);

        $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.restaurants.extend-grace', $restaurant), [
                'days' => 14,
            ])
            ->assertRedirect();

        $restaurant->refresh();
        $this->assertEquals('grace_period', $restaurant->status);
        $this->assertNotNull($restaurant->grace_period_ends_at);
        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'grace_period_extended',
        ]);
    }

    // ─── SuperAdmin creates restaurant with grace period ────────────────

    public function test_new_restaurant_gets_grace_plan_and_status(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Plan::factory()->grace()->create();
        BillingSetting::set('initial_grace_period_days', '14');

        $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.restaurants.store'), [
                'name' => 'Test Billing Restaurant',
                'slug' => 'test-billing',
                'admin_name' => 'Admin',
                'admin_email' => 'admin@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'billing_mode' => 'grace',
            ])
            ->assertRedirect();

        $restaurant = Restaurant::query()->where('slug', 'test-billing')->firstOrFail();
        $this->assertEquals('grace_period', $restaurant->status);
        $this->assertNotNull($restaurant->plan_id);
        $this->assertNotNull($restaurant->grace_period_ends_at);
        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'restaurant_created',
        ]);
    }

    // ─── Admin Subscription Page ────────────────────────────────────────

    public function test_admin_can_view_subscription_page(): void
    {
        $plan = Plan::factory()->pro()->create();
        $restaurant = Restaurant::factory()->create(['plan_id' => $plan->id]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $user->role = 'admin';
        $user->save();

        $this->actingAs($user)
            ->get(route('settings.subscription'))
            ->assertOk();
    }
}
