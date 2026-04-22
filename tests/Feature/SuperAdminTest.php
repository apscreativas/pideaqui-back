<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::factory()->create();
    }

    // ─── Auth ──────────────────────────────────────────────────────────────────

    public function test_superadmin_can_logout(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'superadmin')->post(route('super.logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest('superadmin');
    }

    public function test_unauthenticated_user_redirected_from_dashboard(): void
    {
        $response = $this->get(route('super.dashboard'));

        $response->assertRedirect(route('login'));
    }

    // ─── Dashboard ─────────────────────────────────────────────────────────────

    public function test_superadmin_can_view_dashboard(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Dashboard')
            ->has('mrr')
            ->has('active_subscriptions')
            ->has('total_restaurants')
            ->has('new_this_month')
            ->has('by_status')
            ->has('by_plan')
            ->has('alerts')
            ->has('recent_events')
        );
    }

    public function test_dashboard_shows_correct_kpis(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Restaurant::factory()->count(2)->create(['is_active' => true, 'status' => 'active']);
        Restaurant::factory()->create(['is_active' => false, 'status' => 'disabled']);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertInertia(fn ($page) => $page->where('total_restaurants', 3));
    }

    public function test_dashboard_exposes_grace_expiring_soon_count(): void
    {
        $superAdmin = $this->createSuperAdmin();

        // Expiring in 2 days → should count.
        Restaurant::factory()->create([
            'is_active' => true,
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(2),
        ]);
        // Expiring in 10 days → should NOT count.
        Restaurant::factory()->create([
            'is_active' => true,
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(10),
        ]);
        // Already expired → should NOT count (only *upcoming* within 3 days).
        Restaurant::factory()->create([
            'is_active' => true,
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->subDay(),
        ]);
        // Inactive → should NOT count even if within window.
        Restaurant::factory()->create([
            'is_active' => false,
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDay(),
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertInertia(fn ($page) => $page->where('alerts.grace_expiring_soon', 1));
    }

    public function test_dashboard_exposes_orders_near_limit_count(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $nearLimit = Restaurant::factory()->create([
            'is_active' => true,
            'status' => 'active',
            'orders_limit' => 10,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
        ]);
        // 8 orders → 80% exactly → counts.
        \App\Models\Branch::factory()->create(['restaurant_id' => $nearLimit->id]);
        \App\Models\Order::factory()->count(8)->create([
            'restaurant_id' => $nearLimit->id,
            'created_at' => now(),
        ]);

        $farFromLimit = Restaurant::factory()->create([
            'is_active' => true,
            'status' => 'active',
            'orders_limit' => 100,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
        ]);
        \App\Models\Branch::factory()->create(['restaurant_id' => $farFromLimit->id]);
        \App\Models\Order::factory()->count(5)->create([
            'restaurant_id' => $farFromLimit->id,
            'created_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertInertia(fn ($page) => $page->where('alerts.orders_near_limit', 1));
    }

    public function test_dashboard_exposes_billing_manual_count(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Restaurant::factory()->count(3)->create([
            'is_active' => true,
            'billing_mode' => 'manual',
        ]);
        Restaurant::factory()->create([
            'is_active' => true,
            'billing_mode' => 'subscription',
        ]);
        // Inactive manual → should NOT count.
        Restaurant::factory()->create([
            'is_active' => false,
            'billing_mode' => 'manual',
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertInertia(fn ($page) => $page->where('alerts.billing_manual', 3));
    }

    public function test_dashboard_exposes_new_this_week_split_by_source(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Restaurant::factory()->count(2)->create([
            'is_active' => true,
            'signup_source' => 'self_signup',
            'created_at' => now()->subDays(2),
        ]);
        Restaurant::factory()->create([
            'is_active' => true,
            'signup_source' => 'super_admin',
            'created_at' => now()->subDays(3),
        ]);
        // Older than a week → not counted.
        Restaurant::factory()->create([
            'is_active' => true,
            'signup_source' => 'self_signup',
            'created_at' => now()->subDays(10),
        ]);
        // Inactive → not counted.
        Restaurant::factory()->create([
            'is_active' => false,
            'signup_source' => 'self_signup',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('alerts.new_this_week.total', 3)
            ->where('alerts.new_this_week.self_signup', 2)
            ->where('alerts.new_this_week.super_admin', 1)
        );
    }

    // ─── Restaurants Index ──────────────────────────────────────────────────────

    public function test_superadmin_can_view_restaurants_list(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->count(3)->create();

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.restaurants.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('SuperAdmin/Restaurants/Index'));
    }

    public function test_superadmin_can_filter_restaurants_by_status(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->create(['is_active' => true]);
        Restaurant::factory()->create(['is_active' => false]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['status' => '1']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('restaurants.total', 1)
        );
    }

    public function test_restaurants_index_filter_by_alert_billing_manual(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Restaurant::factory()->count(2)->create([
            'is_active' => true, 'billing_mode' => 'manual',
        ]);
        Restaurant::factory()->create([
            'is_active' => true, 'billing_mode' => 'subscription',
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'billing_manual']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 2));
    }

    public function test_restaurants_index_filter_by_alert_grace_expiring(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Restaurant::factory()->create([
            'is_active' => true, 'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(2),
        ]);
        Restaurant::factory()->create([
            'is_active' => true, 'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(10),
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'grace_expiring']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 1));
    }

    public function test_restaurants_index_filter_by_alert_new_this_week(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Restaurant::factory()->create([
            'is_active' => true, 'created_at' => now()->subDays(3),
        ]);
        Restaurant::factory()->create([
            'is_active' => true, 'created_at' => now()->subDays(10),
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'new_this_week']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 1));
    }

    public function test_restaurants_index_filter_by_alert_past_due(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->count(2)->create(['status' => 'past_due']);
        Restaurant::factory()->create(['status' => 'active']);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'past_due']));

        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 2));
    }

    public function test_restaurants_index_filter_by_alert_grace_period(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->create(['status' => 'grace_period', 'grace_period_ends_at' => now()->addDays(10)]);
        Restaurant::factory()->create(['status' => 'grace_period', 'grace_period_ends_at' => now()->addDay()]);
        Restaurant::factory()->create(['status' => 'active']);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'grace_period']));

        // Both grace_period restaurants regardless of remaining days.
        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 2));
    }

    public function test_restaurants_index_filter_by_alert_suspended(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->create(['status' => 'suspended']);
        Restaurant::factory()->create(['status' => 'active']);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'suspended']));

        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 1));
    }

    public function test_restaurants_index_filter_by_alert_no_subscription(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->create(['status' => 'active', 'stripe_id' => null]);
        Restaurant::factory()->create(['status' => 'active', 'stripe_id' => 'cus_test']);
        // Disabled should NOT count.
        Restaurant::factory()->create(['status' => 'disabled', 'stripe_id' => null]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'no_subscription']));

        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 1));
    }

    public function test_restaurants_index_filter_by_alert_orders_near_limit(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $near = Restaurant::factory()->create([
            'is_active' => true, 'status' => 'active',
            'orders_limit' => 10,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
        ]);
        \App\Models\Branch::factory()->create(['restaurant_id' => $near->id]);
        \App\Models\Order::factory()->count(8)->create([
            'restaurant_id' => $near->id, 'created_at' => now(),
        ]);

        $far = Restaurant::factory()->create([
            'is_active' => true, 'status' => 'active',
            'orders_limit' => 100,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
        ]);
        \App\Models\Branch::factory()->create(['restaurant_id' => $far->id]);
        \App\Models\Order::factory()->count(5)->create([
            'restaurant_id' => $far->id, 'created_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.index', ['alert' => 'orders_near_limit']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('restaurants.total', 1));
    }

    // ─── Create Restaurant ──────────────────────────────────────────────────────

    public function test_superadmin_can_view_create_restaurant_form(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.restaurants.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('SuperAdmin/Restaurants/Create'));
    }

    public function test_superadmin_can_create_restaurant(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'superadmin')->post(route('super.restaurants.store'), [
            'name' => 'Tacos El Rey',
            'admin_name' => 'Carlos López',
            'admin_email' => 'carlos@tacosrey.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'billing_mode' => 'grace',
        ]);

        $response->assertRedirect();

        $restaurant = Restaurant::where('name', 'Tacos El Rey')->firstOrFail();
        $this->assertDatabaseHas('restaurants', [
            'name' => 'Tacos El Rey',
            'slug' => 'tacos-el-rey',
            'is_active' => true,
            'status' => 'grace_period',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'carlos@tacosrey.com',
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertDatabaseHas('payment_methods', ['restaurant_id' => $restaurant->id, 'type' => 'cash', 'is_active' => true]);
        $this->assertDatabaseHas('payment_methods', ['restaurant_id' => $restaurant->id, 'type' => 'terminal', 'is_active' => false]);
        $this->assertDatabaseHas('payment_methods', ['restaurant_id' => $restaurant->id, 'type' => 'transfer', 'is_active' => false]);

        $this->assertEquals('super_admin', $restaurant->fresh()->signup_source);
    }

    public function test_auto_generates_unique_slug_when_name_collides(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->create(['name' => 'Tacos El Rey', 'slug' => 'tacos-el-rey']);

        $response = $this->actingAs($superAdmin, 'superadmin')->post(route('super.restaurants.store'), [
            'name' => 'Tacos El Rey',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'billing_mode' => 'manual',
            'orders_limit' => 500,
            'orders_limit_start' => now()->startOfMonth()->toDateString(),
            'orders_limit_end' => now()->endOfMonth()->toDateString(),
            'max_branches' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurants', ['slug' => 'tacos-el-rey-2']);
    }

    public function test_create_restaurant_fails_with_duplicate_admin_email(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create();
        User::factory()->create(['restaurant_id' => $restaurant->id, 'email' => 'taken@test.com']);

        $response = $this->actingAs($superAdmin, 'superadmin')->post(route('super.restaurants.store'), [
            'name' => 'Test',
            'admin_name' => 'Admin',
            'admin_email' => 'taken@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'billing_mode' => 'manual',
            'orders_limit' => 500,
            'orders_limit_start' => now()->startOfMonth()->toDateString(),
            'orders_limit_end' => now()->endOfMonth()->toDateString(),
            'max_branches' => 3,
        ]);

        $response->assertSessionHasErrors('admin_email');
    }

    public function test_superadmin_can_send_verification_email_to_admin(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.restaurants.send-verification', $restaurant));

        $response->assertRedirect();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $admin,
            \App\Notifications\VerifyEmailNotification::class,
        );

        // Pre-verified admin stays verified — endpoint only sends the email.
        $this->assertNotNull($admin->fresh()->email_verified_at);

        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'verification_email_sent_manually',
            'actor_type' => 'super_admin',
        ]);
    }

    public function test_restaurant_creation_rolls_back_when_billing_audit_fails(): void
    {
        $superAdmin = $this->createSuperAdmin();

        \Illuminate\Support\Facades\Schema::drop('billing_audits');

        try {
            $this->actingAs($superAdmin, 'superadmin')->post(route('super.restaurants.store'), [
                'name' => 'Rollback Test Restaurant',
                'admin_name' => 'Rollback Admin',
                'admin_email' => 'rollback@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'billing_mode' => 'grace',
            ]);
        } catch (\Throwable $e) {
            // Expected: transaction throws because billing_audits table is gone.
        }

        $this->assertDatabaseMissing('restaurants', ['name' => 'Rollback Test Restaurant']);
        $this->assertDatabaseMissing('users', ['email' => 'rollback@test.com']);
    }

    // ─── Show Restaurant ────────────────────────────────────────────────────────

    public function test_superadmin_can_view_restaurant_detail(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create();

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')
            ->get(route('super.restaurants.show', $restaurant));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Restaurants/Show')
            ->has('orders_count')
            ->has('branch_count')
        );
    }

    // ─── Update Limits ──────────────────────────────────────────────────────────

    public function test_superadmin_can_update_restaurant_limits(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500, 'max_branches' => 3]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->put(route('super.restaurants.update-limits', $restaurant), [
                'orders_limit' => 1000,
                'orders_limit_start' => now()->startOfMonth()->toDateString(),
                'orders_limit_end' => now()->endOfMonth()->toDateString(),
                'max_branches' => 5,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'orders_limit' => 1000,
            'max_branches' => 5,
        ]);
    }

    public function test_cannot_set_limits_below_current_period_orders(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        // Create 10 orders within the period
        Order::factory()->count(10)->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => Branch::factory()->create(['restaurant_id' => $restaurant->id])->id,
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->put(route('super.restaurants.update-limits', $restaurant), [
                'orders_limit' => 5,
                'orders_limit_start' => now()->startOfMonth()->toDateString(),
                'orders_limit_end' => now()->endOfMonth()->toDateString(),
                'max_branches' => 3,
            ]);

        $response->assertSessionHasErrors('orders_limit');
    }

    // ─── Toggle Active ──────────────────────────────────────────────────────────

    public function test_superadmin_can_toggle_restaurant_active(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->patch(route('super.restaurants.toggle', $restaurant));

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'is_active' => false]);
    }

    public function test_superadmin_can_reactivate_restaurant(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create(['is_active' => false]);

        $this->actingAs($superAdmin, 'superadmin')
            ->patch(route('super.restaurants.toggle', $restaurant));

        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'is_active' => true]);
    }

    // ─── Statistics ─────────────────────────────────────────────────────────────

    public function test_superadmin_can_view_statistics(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.statistics'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Statistics')
            ->has('orders_by_day')
            ->has('top_restaurants')
        );
    }

    // ─── Admin restaurante no puede acceder a rutas SuperAdmin ─────────────────

    public function test_restaurant_admin_cannot_access_superadmin_dashboard(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($user)->get(route('super.dashboard'));

        // Authenticated as web user but not superadmin guard — redirected
        $response->assertRedirect(route('login'));
    }
}
