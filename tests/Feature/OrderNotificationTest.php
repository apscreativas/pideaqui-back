<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockGoogleMaps();
    }

    private function mockGoogleMaps(): void
    {
        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => 1.5, 'duration_minutes' => 5],
        ]);
        $this->instance(GoogleMapsService::class, $mock);
    }

    private function restaurant(array $attrs = []): Restaurant
    {
        $restaurant = Restaurant::factory()->create(array_merge([
            'is_active' => true,
            'orders_limit' => 100,
            'allows_delivery' => true,
            'allows_pickup' => true,
            'notify_new_orders' => true,
        ], $attrs));

        PaymentMethod::factory()->cash()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        return $restaurant;
    }

    private function createAdmin(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $user->restaurant_id = $restaurant->id;
        $user->save();

        return $user;
    }

    private function orderPayload(Branch $branch, Product $product): array
    {
        return [
            'customer' => ['token' => 'uuid-notif-001', 'name' => 'Test Customer', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []],
            ],
        ];
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_notification_sent_on_new_order(): void
    {
        Notification::fake();

        $restaurant = $this->restaurant();
        $this->createAdmin($restaurant);
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $this->postJson(
            "/api/public/{$restaurant->slug}/orders",
            $this->orderPayload($branch, $product),
        )
            ->assertCreated();

        Notification::assertSentTo($restaurant, NewOrderNotification::class);
    }

    public function test_notification_not_sent_when_disabled(): void
    {
        Notification::fake();

        $restaurant = $this->restaurant(['notify_new_orders' => false]);
        $this->createAdmin($restaurant);
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $this->postJson(
            "/api/public/{$restaurant->slug}/orders",
            $this->orderPayload($branch, $product),
        )
            ->assertCreated();

        Notification::assertNotSentTo($restaurant, NewOrderNotification::class);
    }

    public function test_notification_contains_order_details(): void
    {
        Notification::fake();

        $restaurant = $this->restaurant();
        $this->createAdmin($restaurant);
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true, 'name' => 'Sucursal Centro']);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true, 'name' => 'Enchiladas', 'price' => 85.00]);

        $this->postJson(
            "/api/public/{$restaurant->slug}/orders",
            $this->orderPayload($branch, $product),
        )
            ->assertCreated();

        Notification::assertSentTo($restaurant, NewOrderNotification::class, function (NewOrderNotification $notification) {
            $order = $notification->order;

            return $order->customer->name === 'Test Customer'
                && $order->branch->name === 'Sucursal Centro'
                && $order->items->first()->product->name === 'Enchiladas';
        });
    }

    public function test_notification_routes_to_all_restaurant_users(): void
    {
        $restaurant = $this->restaurant();
        $admin1 = $this->createAdmin($restaurant);
        $admin2 = $this->createAdmin($restaurant);

        $restaurant->load('users');
        $emails = $restaurant->routeNotificationForMail();

        $this->assertCount(2, $emails);
        $this->assertContains($admin1->email, $emails);
        $this->assertContains($admin2->email, $emails);
    }

    public function test_failed_notification_does_not_block_order(): void
    {
        $restaurant = $this->restaurant();
        $this->createAdmin($restaurant);
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $response = $this->postJson(
            "/api/public/{$restaurant->slug}/orders",
            $this->orderPayload($branch, $product),
        );

        $response->assertCreated();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_toggle_notification_preference(): void
    {
        $restaurant = $this->restaurant(['notify_new_orders' => true]);
        $admin = $this->createAdmin($restaurant);

        $this->actingAs($admin)->put(route('settings.general.update'), [
            'name' => $restaurant->name,
            'notify_new_orders' => false,
        ])->assertRedirect();

        $this->assertFalse($restaurant->fresh()->notify_new_orders);
    }

    public function test_general_page_shows_notification_toggle(): void
    {
        $restaurant = $this->restaurant();
        $admin = $this->createAdmin($restaurant);

        $this->actingAs($admin)->get(route('settings.general'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/General')
                ->has('restaurant.notify_new_orders')
            );
    }
}
