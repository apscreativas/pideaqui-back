<?php

namespace Tests\Feature;

use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500]);
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

    // ─── OrderStatusChanged ──────────────────────────────────────────────────────

    public function test_advancing_status_broadcasts_order_status_changed(): void
    {
        Event::fake([OrderStatusChanged::class]);

        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'received']);

        $this->actingAs($user)->put(route('orders.advance-status', $order));

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($order) {
            return $event->order->id === $order->id
                && $event->order->status === 'preparing'
                && $event->previousStatus === 'received';
        });
    }

    public function test_order_status_changed_broadcasts_on_private_restaurant_channel(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'received']);
        $order->load(['customer', 'branch']);

        $event = new OrderStatusChanged($order, 'received');
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertStringContainsString('restaurant.'.$restaurant->id, $channels[0]->name);
    }

    public function test_order_status_changed_includes_previous_status_in_payload(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'preparing']);
        $order->load(['customer', 'branch']);

        $event = new OrderStatusChanged($order, 'received');
        $data = $event->broadcastWith();

        $this->assertEquals('received', $data['previous_status']);
        $this->assertEquals($order->id, $data['order']['id']);
        $this->assertEquals('preparing', $data['order']['status']);
    }

    // ─── OrderCancelled ──────────────────────────────────────────────────────────

    public function test_cancelling_order_broadcasts_order_cancelled(): void
    {
        Event::fake([OrderCancelled::class]);

        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'received']);

        $this->actingAs($user)->put(route('orders.cancel', $order), [
            'cancellation_reason' => 'Sin stock',
        ]);

        Event::assertDispatched(OrderCancelled::class, function (OrderCancelled $event) use ($order) {
            return $event->order->id === $order->id
                && $event->order->status === 'cancelled'
                && $event->previousStatus === 'received';
        });
    }

    public function test_order_cancelled_includes_reason_in_payload(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, [
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente no contesta',
            'cancelled_at' => now(),
        ]);
        $order->load(['customer', 'branch']);

        $event = new OrderCancelled($order, 'preparing');
        $data = $event->broadcastWith();

        $this->assertEquals('preparing', $data['previous_status']);
        $this->assertEquals('cancelled', $data['order']['status']);
        $this->assertEquals('Cliente no contesta', $data['order']['cancellation_reason']);
    }

    // ─── OrderCreated ────────────────────────────────────────────────────────────

    public function test_order_created_broadcasts_on_private_restaurant_channel(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'received']);
        $order->load(['customer', 'branch']);

        $event = new OrderCreated($order);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertStringContainsString('restaurant.'.$restaurant->id, $channels[0]->name);
    }

    public function test_order_created_payload_contains_order_data(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'received']);
        $order->load(['customer', 'branch']);

        $event = new OrderCreated($order);
        $data = $event->broadcastWith();

        $this->assertEquals($order->id, $data['order']['id']);
        $this->assertEquals('received', $data['order']['status']);
        $this->assertArrayHasKey('customer', $data['order']);
        $this->assertArrayHasKey('branch', $data['order']);
        $this->assertArrayHasKey('total', $data['order']);
    }

    public function test_creating_order_via_api_broadcasts_order_created(): void
    {
        Event::fake([OrderCreated::class]);

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'test-broadcast-token',
            'orders_limit' => 500,
            'allows_delivery' => false,
            'allows_pickup' => true,
            'allows_dine_in' => false,
        ]);
        $branch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        PaymentMethod::factory()->cash()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        $product = \App\Models\Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
            'price' => 100,
        ]);
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $response = $this->postJson('/api/orders', [
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'customer' => [
                'name' => 'Test',
                'phone' => '1234567890',
                'token' => 'test-customer-token-123',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($restaurant) {
            return $event->order->restaurant_id === $restaurant->id
                && $event->order->status === 'received';
        });
    }

    // ─── No broadcast on error ───────────────────────────────────────────────────

    public function test_no_broadcast_when_advance_status_fails(): void
    {
        Event::fake([OrderStatusChanged::class]);

        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'delivered']);

        $this->actingAs($user)->put(route('orders.advance-status', $order));

        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    public function test_no_broadcast_when_cancel_forbidden(): void
    {
        Event::fake([OrderCancelled::class]);

        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrder($restaurant->id, ['status' => 'on_the_way']);

        $this->actingAs($user)->put(route('orders.cancel', $order), [
            'cancellation_reason' => 'Motivo',
        ]);

        Event::assertNotDispatched(OrderCancelled::class);
    }

    // ─── Channel authorization ───────────────────────────────────────────────────

    public function test_channel_callback_authorizes_own_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $authorized = (int) $user->restaurant_id === $restaurant->id;

        $this->assertTrue($authorized);
    }

    public function test_channel_callback_rejects_other_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();
        $other = Restaurant::factory()->create();

        $authorized = (int) $user->restaurant_id === $other->id;

        $this->assertFalse($authorized);
    }
}
