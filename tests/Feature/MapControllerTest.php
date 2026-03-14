<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    private function createGeolocatedOrder(int $restaurantId, ?int $branchId = null, array $overrides = []): Order
    {
        $branch = $branchId
            ? Branch::find($branchId)
            : Branch::factory()->create(['restaurant_id' => $restaurantId]);
        $customer = Customer::factory()->create();

        return Order::factory()->create(array_merge([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'latitude' => 20.6597,
            'longitude' => -103.3496,
        ], $overrides));
    }

    // ─── Auth ────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_redirected_from_map(): void
    {
        $response = $this->get(route('map.index'));

        $response->assertRedirect(route('login'));
    }

    // ─── Page renders ────────────────────────────────────────────────────────────

    public function test_admin_can_view_map_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Map/Index'));
    }

    public function test_map_page_has_expected_props(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Map/Index')
            ->has('orders')
            ->has('branches')
            ->has('kpis')
            ->has('kpis.total')
            ->has('kpis.active')
            ->has('kpis.delivered')
            ->has('kpis.cancelled')
            ->has('kpis.revenue')
            ->has('kpis.geolocated')
            ->has('filters')
            ->has('filters.from')
            ->has('filters.to')
            ->has('allBranches')
            ->has('mapsKey')
        );
    }

    // ─── Orders only with coordinates ─────────────────────────────────────────

    public function test_map_only_returns_geolocated_orders(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createGeolocatedOrder($restaurant->id);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['latitude' => null, 'longitude' => null]);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('orders', 1)
            ->where('kpis.geolocated', 1)
        );
    }

    // ─── KPIs ──────────────────────────────────────────────────────────────────

    public function test_kpis_count_all_orders_including_non_geolocated(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'delivered']);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'received']);
        $this->createGeolocatedOrder($restaurant->id, overrides: [
            'status' => 'preparing',
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'cancelled']);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('kpis.total', 4)
            ->where('kpis.active', 2)
            ->where('kpis.delivered', 1)
            ->where('kpis.cancelled', 1)
            ->where('kpis.geolocated', 3)
        );
    }

    public function test_kpis_revenue_sums_delivered_orders(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'delivered', 'total' => 150.50]);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'delivered', 'total' => 249.50]);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'cancelled', 'total' => 100.00]);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('kpis.revenue', 400)
        );
    }

    // ─── Multitenancy ──────────────────────────────────────────────────────────

    public function test_map_data_scoped_to_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createGeolocatedOrder($restaurant->id);

        $other = Restaurant::factory()->create();
        $this->createGeolocatedOrder($other->id);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('orders', 1)
            ->where('kpis.total', 1)
        );
    }

    // ─── Date filter ──────────────────────────────────────────────────────────

    public function test_map_filters_by_date_range(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createGeolocatedOrder($restaurant->id, overrides: ['created_at' => now()]);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['created_at' => now()->subDays(5)]);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertInertia(fn ($page) => $page
            ->has('orders', 1)
            ->where('kpis.total', 1)
        );
    }

    // ─── Branch filter ──────────────────────────────────────────────────────────

    public function test_map_filters_by_branch(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $branchA = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $branchB = Branch::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->createGeolocatedOrder($restaurant->id, $branchA->id);
        $this->createGeolocatedOrder($restaurant->id, $branchA->id);
        $this->createGeolocatedOrder($restaurant->id, $branchB->id);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index', [
            'branch_id' => $branchA->id,
        ]));

        $response->assertInertia(fn ($page) => $page
            ->has('orders', 2)
            ->where('kpis.total', 2)
        );
    }

    // ─── Status filter ──────────────────────────────────────────────────────────

    public function test_map_filters_by_statuses(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'received']);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'delivered']);
        $this->createGeolocatedOrder($restaurant->id, overrides: ['status' => 'cancelled']);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index', [
            'statuses' => 'received,delivered',
        ]));

        $response->assertInertia(fn ($page) => $page
            ->has('orders', 2)
        );
    }

    // ─── Branches returned ──────────────────────────────────────────────────────

    public function test_map_returns_active_branches_only(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => false]);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('branches', 2)
            ->has('allBranches', 2)
        );
    }

    // ─── Empty state ────────────────────────────────────────────────────────────

    public function test_map_empty_state_returns_zero_kpis(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('orders', 0)
            ->where('kpis.total', 0)
            ->where('kpis.active', 0)
            ->where('kpis.delivered', 0)
            ->where('kpis.cancelled', 0)
            ->where('kpis.revenue', 0)
            ->where('kpis.geolocated', 0)
        );
    }

    // ─── Orders include relations ─────────────────────────────────────────────

    public function test_map_orders_include_customer_and_branch(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createGeolocatedOrder($restaurant->id);

        $response = $this->withoutVite()->actingAs($user)->get(route('map.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('orders', 1)
            ->has('orders.0.customer')
            ->has('orders.0.branch')
            ->has('orders.0.latitude')
            ->has('orders.0.longitude')
            ->has('orders.0.status')
        );
    }
}
