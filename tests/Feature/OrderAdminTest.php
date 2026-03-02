<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['max_monthly_orders' => 500]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    private function createOrder(int $restaurantId, array $overrides = []): Order
    {
        $branch = Branch::factory()->create(['restaurant_id' => $restaurantId]);
        $customer = Customer::factory()->create();

        return Order::factory()->create(array_merge([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
        ], $overrides));
    }

    // ─── Kanban index ──────────────────────────────────────────────────────────

    public function test_admin_can_view_orders_kanban(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Orders/Index'));
    }

    public function test_orders_index_has_expected_props(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders')
            ->has('orders.received')
            ->has('orders.preparing')
            ->has('orders.on_the_way')
            ->has('orders.delivered')
            ->has('branches')
            ->has('filters')
            ->has('monthly_count')
            ->has('max_monthly_orders')
        );
    }

    public function test_orders_are_grouped_by_status(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, ['status' => 'received']);
        $this->createOrder($restaurant->id, ['status' => 'received']);
        $this->createOrder($restaurant->id, ['status' => 'preparing']);
        $this->createOrder($restaurant->id, ['status' => 'delivered']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('orders.received', 2)
            ->has('orders.preparing', 1)
            ->has('orders.on_the_way', 0)
            ->has('orders.delivered', 1)
        );
    }

    public function test_admin_cannot_see_orders_from_another_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $other = Restaurant::factory()->create();
        $this->createOrder($other->id, ['status' => 'received']);

        $this->createOrder($restaurant->id, ['status' => 'received']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.index'));

        $response->assertInertia(fn ($page) => $page->has('orders.received', 1));
    }

    // ─── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_view_order_detail(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.show', $order));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Orders/Show'));
    }

    public function test_admin_cannot_view_order_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $other = Restaurant::factory()->create();
        $otherOrder = $this->createOrder($other->id);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.show', $otherOrder));

        $response->assertStatus(404);
    }

    // ─── Advance status ────────────────────────────────────────────────────────

    public function test_admin_can_advance_order_status_from_received_to_preparing(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'received']);

        $response = $this->actingAs($user)->put(route('orders.advance-status', $order));

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'preparing']);
    }

    public function test_admin_can_advance_order_status_from_preparing_to_on_the_way(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'preparing']);

        $this->actingAs($user)->put(route('orders.advance-status', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'on_the_way']);
    }

    public function test_admin_can_advance_order_status_from_on_the_way_to_delivered(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'on_the_way']);

        $this->actingAs($user)->put(route('orders.advance-status', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_delivered_order_cannot_be_advanced_further(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'delivered']);

        $response = $this->actingAs($user)->put(route('orders.advance-status', $order));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_admin_cannot_advance_status_of_order_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $other = Restaurant::factory()->create();
        $otherOrder = $this->createOrder($other->id, ['status' => 'received']);

        $response = $this->actingAs($user)->put(route('orders.advance-status', $otherOrder));

        $response->assertStatus(404);
        $this->assertDatabaseHas('orders', ['id' => $otherOrder->id, 'status' => 'received']);
    }

    // ─── New count ─────────────────────────────────────────────────────────────

    public function test_new_count_returns_received_orders_count(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, ['status' => 'received']);
        $this->createOrder($restaurant->id, ['status' => 'received']);
        $this->createOrder($restaurant->id, ['status' => 'preparing']);

        $response = $this->actingAs($user)->get(route('orders.new-count'));

        $response->assertStatus(200);
        $response->assertJson(['count' => 2]);
    }

    // ─── Auth ──────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_redirected_from_orders(): void
    {
        $response = $this->get(route('orders.index'));

        $response->assertRedirect(route('login'));
    }
}
