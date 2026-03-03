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

    public function test_superadmin_can_view_login_page(): void
    {
        $response = $this->withoutVite()->get(route('super.login'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('SuperAdmin/Login'));
    }

    public function test_superadmin_can_login_with_valid_credentials(): void
    {
        $superAdmin = SuperAdmin::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->post(route('super.login'), [
            'email' => $superAdmin->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('super.dashboard'));
        $this->assertAuthenticatedAs($superAdmin, 'superadmin');
    }

    public function test_superadmin_cannot_login_with_wrong_password(): void
    {
        $superAdmin = SuperAdmin::factory()->create(['password' => bcrypt('correct')]);

        $response = $this->post(route('super.login'), [
            'email' => $superAdmin->email,
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('superadmin');
    }

    public function test_superadmin_can_logout(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'superadmin')->post(route('super.logout'));

        $response->assertRedirect(route('super.login'));
        $this->assertGuest('superadmin');
    }

    public function test_unauthenticated_user_redirected_from_dashboard(): void
    {
        $response = $this->get(route('super.dashboard'));

        $response->assertRedirect(route('super.login'));
    }

    // ─── Dashboard ─────────────────────────────────────────────────────────────

    public function test_superadmin_can_view_dashboard(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Dashboard')
            ->has('active_restaurants')
            ->has('new_restaurants_this_month')
            ->has('total_monthly_orders')
            ->has('recent_restaurants')
        );
    }

    public function test_dashboard_shows_correct_kpis(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Restaurant::factory()->count(2)->create(['is_active' => true]);
        Restaurant::factory()->create(['is_active' => false]);

        $response = $this->withoutVite()->actingAs($superAdmin, 'superadmin')->get(route('super.dashboard'));

        $response->assertInertia(fn ($page) => $page->where('active_restaurants', 2));
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
            'slug' => 'tacos-el-rey',
            'admin_name' => 'Carlos López',
            'admin_email' => 'carlos@tacosrey.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'orders_limit' => 300,
            'orders_limit_start' => now()->startOfMonth()->toDateString(),
            'orders_limit_end' => now()->endOfMonth()->toDateString(),
            'max_branches' => 2,
        ]);

        $response->assertRedirect();

        $restaurant = Restaurant::where('slug', 'tacos-el-rey')->firstOrFail();
        $this->assertDatabaseHas('restaurants', [
            'name' => 'Tacos El Rey',
            'slug' => 'tacos-el-rey',
            'orders_limit' => 300,
            'max_branches' => 2,
            'is_active' => true,
        ]);

        $this->assertNotNull($restaurant->access_token);
        $this->assertEquals(64, strlen($restaurant->access_token));

        $this->assertDatabaseHas('users', [
            'email' => 'carlos@tacosrey.com',
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertDatabaseHas('payment_methods', ['restaurant_id' => $restaurant->id, 'type' => 'cash', 'is_active' => true]);
        $this->assertDatabaseHas('payment_methods', ['restaurant_id' => $restaurant->id, 'type' => 'terminal', 'is_active' => false]);
        $this->assertDatabaseHas('payment_methods', ['restaurant_id' => $restaurant->id, 'type' => 'transfer', 'is_active' => false]);
    }

    public function test_create_restaurant_fails_with_duplicate_slug(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Restaurant::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($superAdmin, 'superadmin')->post(route('super.restaurants.store'), [
            'name' => 'Test',
            'slug' => 'existing-slug',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'orders_limit' => 500,
            'orders_limit_start' => now()->startOfMonth()->toDateString(),
            'orders_limit_end' => now()->endOfMonth()->toDateString(),
            'max_branches' => 3,
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_create_restaurant_fails_with_duplicate_admin_email(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create();
        User::factory()->create(['restaurant_id' => $restaurant->id, 'email' => 'taken@test.com']);

        $response = $this->actingAs($superAdmin, 'superadmin')->post(route('super.restaurants.store'), [
            'name' => 'Test',
            'slug' => 'test-new',
            'admin_name' => 'Admin',
            'admin_email' => 'taken@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'orders_limit' => 500,
            'orders_limit_start' => now()->startOfMonth()->toDateString(),
            'orders_limit_end' => now()->endOfMonth()->toDateString(),
            'max_branches' => 3,
        ]);

        $response->assertSessionHasErrors('admin_email');
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

    // ─── Regenerate Token ──────────────────────────────────────────────────────

    public function test_superadmin_can_regenerate_restaurant_token(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $restaurant = Restaurant::factory()->create();
        $oldToken = $restaurant->access_token;

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.restaurants.regenerate-token', $restaurant));

        $response->assertRedirect();
        $restaurant->refresh();
        $this->assertNotEquals($oldToken, $restaurant->access_token);
        $this->assertEquals(64, strlen($restaurant->access_token));
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
        $response->assertRedirect(route('super.login'));
    }
}
