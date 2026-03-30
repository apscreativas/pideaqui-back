<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryRange;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default Google Maps mock: 1.5 km driving distance for standard test coords.
        $this->mockGoogleMaps();
    }

    private function mockGoogleMaps(float $distanceKm = 1.5, int $durationMinutes = 5): void
    {
        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => $distanceKm, 'duration_minutes' => $durationMinutes],
        ]);
        $this->instance(GoogleMapsService::class, $mock);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function restaurant(array $attrs = []): Restaurant
    {
        $restaurant = Restaurant::factory()->create(array_merge([
            'access_token' => 'test-order-token',
            'is_active' => true,
            'orders_limit' => 100,
            'allows_delivery' => true,
            'allows_pickup' => true,
            'allows_dine_in' => true,
        ], $attrs));

        PaymentMethod::factory()->cash()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        // Create schedule for current day so restaurant is "open" during tests.
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        return $restaurant;
    }

    private function authHeaders(Restaurant $restaurant): array
    {
        return ['Authorization' => 'Bearer '.$restaurant->access_token];
    }

    private function branch(Restaurant $restaurant): Branch
    {
        return Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'whatsapp' => '+5215512345678',
            'is_active' => true,
            'latitude' => 19.430000,  // ~1.1 km from client coords in deliveryPayload
            'longitude' => -99.110000,
        ]);
    }

    private function withDeliveryRange(Restaurant $restaurant, float $minKm = 0, float $maxKm = 10, float $price = 30.00): DeliveryRange
    {
        return DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => $minKm,
            'max_km' => $maxKm,
            'price' => $price,
        ]);
    }

    private function product(Restaurant $restaurant, float $price = 25.00): Product
    {
        return Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function deliveryPayload(Branch $branch, Product $product, array $overrides = []): array
    {
        return array_merge([
            'customer' => ['token' => 'uuid-test-001', 'name' => 'María García', 'phone' => '5598765432'],
            'delivery_type' => 'delivery',
            'branch_id' => $branch->id,
            'address_street' => 'Calle Morelos',
            'address_number' => '45',
            'address_colony' => 'Roma Norte',
            'address_references' => 'Entre Orizaba y Tonalá',
            'latitude' => 19.420000,
            'longitude' => -99.110000,
            'distance_km' => 3.2,
            'delivery_cost' => 30.00,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => $product->price, 'modifiers' => []],
            ],
        ], $overrides);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_missing_required_fields_returns_422(): void
    {
        $restaurant = $this->restaurant();

        $this->postJson('/api/orders', [], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer', 'delivery_type', 'branch_id', 'payment_method', 'items']);
    }

    public function test_delivery_type_requires_address_and_coordinates(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->postJson('/api/orders', [
            'customer' => ['token' => 'abc', 'name' => 'Test', 'phone' => '1234567890'],
            'delivery_type' => 'delivery',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []]],
        ], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_street', 'address_number', 'address_colony', 'latitude', 'longitude', 'distance_km', 'delivery_cost']);
    }

    // ─── Happy paths ─────────────────────────────────────────────────────────

    public function test_create_delivery_order_returns_201_with_correct_structure(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        );

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['order_id', 'order_number', 'branch_whatsapp', 'whatsapp_message']])
            ->assertJsonPath('data.branch_whatsapp', '+5215512345678');
    }

    public function test_order_is_persisted_in_database(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'delivery_type' => 'delivery',
            'status' => 'received',
            'payment_method' => 'cash',
        ]);
    }

    public function test_backend_calculates_subtotal_and_total(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);  // 2 units × $25 = $50
        $this->withDeliveryRange($restaurant, 0, 10, 30.00);

        $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $order = Order::latest()->first();
        $this->assertEquals('50.00', $order->subtotal);
        $this->assertEquals('30.00', $order->delivery_cost);  // from delivery range, not client
        $this->assertEquals('80.00', $order->total);
    }

    public function test_order_with_modifiers_calculates_correctly(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant, 0, 10, 30.00);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
        ]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 15.00,
        ]);

        $payload = $this->deliveryPayload($branch, $product, [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 25.00,
                'modifiers' => [['modifier_option_id' => $option->id, 'price_adjustment' => 15.00]],
            ]],
        ]);

        $this->postJson('/api/orders', $payload, $this->authHeaders($restaurant))
            ->assertCreated();

        // subtotal = (25 + 15) × 1 = 40; delivery = 30; total = 70
        $order = Order::latest()->first();
        $this->assertEquals('40.00', $order->subtotal);
        $this->assertEquals('70.00', $order->total);
    }

    public function test_pickup_order_has_zero_delivery_cost(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $this->postJson('/api/orders', [
            'customer' => ['token' => 'uuid-test-002', 'name' => 'Juan', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []]],
        ], $this->authHeaders($restaurant))->assertCreated();

        $order = Order::latest()->first();
        $this->assertEquals('0.00', $order->delivery_cost);
        $this->assertEquals($order->subtotal, $order->total);
    }

    public function test_creates_or_updates_customer_record(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $this->withDeliveryRange($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product, [
                'customer' => ['token' => 'unique-token-abc', 'name' => 'Ana López', 'phone' => '5512345678'],
            ]),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertDatabaseHas('customers', ['token' => 'unique-token-abc', 'name' => 'Ana López']);
    }

    public function test_whatsapp_message_contains_product_and_totals(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $product->update(['name' => 'Taco de Bistec']);
        $this->withDeliveryRange($restaurant, 0, 10, 30.00);

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('Taco de Bistec', $message);
        $this->assertStringContainsString('$50.00', $message);  // 2 × $25
        $this->assertStringContainsString('$80.00', $message);  // total
        $this->assertStringContainsString('A domicilio', $message);
        $this->assertStringContainsString($restaurant->name, $message);
        $this->assertStringContainsString('María García', $message);
        $this->assertStringContainsString('5598765432', $message);
    }

    public function test_order_number_is_zero_padded(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $this->withDeliveryRange($restaurant);

        $response = $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertMatchesRegularExpression('/^#\d{4,}$/', $response->json('data.order_number'));
    }

    // ─── Business rule failures ───────────────────────────────────────────────

    public function test_monthly_limit_reached_returns_422(): void
    {
        $restaurant = $this->restaurant(['orders_limit' => 1]);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $customer = Customer::factory()->create();
        $this->withDeliveryRange($restaurant);

        // Create 1 order to hit the limit.
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'status' => 'received',
        ]);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonPath('error', 'monthly_limit_reached');
    }

    public function test_expired_period_returns_422(): void
    {
        $restaurant = $this->restaurant([
            'orders_limit' => 100,
            'orders_limit_start' => now()->subMonth()->startOfMonth(),
            'orders_limit_end' => now()->subDays(3),
        ]);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $this->withDeliveryRange($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonPath('error', 'monthly_limit_reached');
    }

    public function test_period_not_started_returns_422(): void
    {
        $restaurant = $this->restaurant([
            'orders_limit' => 100,
            'orders_limit_start' => now()->addDays(3),
            'orders_limit_end' => now()->addMonth(),
        ]);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $this->withDeliveryRange($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonPath('error', 'monthly_limit_reached');
    }

    public function test_branch_from_other_restaurant_returns_422(): void
    {
        $restaurant = $this->restaurant(['allows_pickup' => true]);
        $otherRestaurant = Restaurant::factory()->create(['is_active' => true]);
        $foreignBranch = $this->branch($otherRestaurant);
        $product = $this->product($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($foreignBranch, $product, ['delivery_type' => 'pickup']),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_inactive_branch_returns_422(): void
    {
        $restaurant = $this->restaurant(['allows_pickup' => true]);
        $branch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'whatsapp' => '+5215512345678',
            'is_active' => false,
        ]);
        $product = $this->product($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product, ['delivery_type' => 'pickup']),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_inactive_product_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'price' => 25.00,
            'is_active' => false,
        ]);
        $this->withDeliveryRange($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_delivery_without_matching_range_returns_422(): void
    {
        $this->mockGoogleMaps(3.5); // Override: 3.5 km driving — outside 0-2 km range.

        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        // Range only covers 0-2 km, but driving distance is 3.5 km.
        $this->withDeliveryRange($restaurant, 0, 2, 30.00);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product, [
                'latitude' => 19.460000,
                'longitude' => -99.110000,
            ]),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_cost']);
    }

    public function test_delivery_cost_is_calculated_from_range_not_client(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        // Range price is $50, but client sends $30
        $this->withDeliveryRange($restaurant, 0, 10, 50.00);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product, ['delivery_cost' => 30.00]),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $order = Order::latest()->first();
        $this->assertEquals('50.00', $order->delivery_cost);  // server-side from range
        $this->assertEquals('100.00', $order->total);  // 50 subtotal + 50 delivery
    }

    public function test_required_modifier_group_must_have_selection(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'is_required' => true,
        ]);
        ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 5.00,
        ]);

        // Send order with no modifiers — should fail because group is required.
        $this->postJson('/api/orders', [
            'customer' => ['token' => 'uuid-test-req', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []]],
        ], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_price_tampering_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $payload = $this->deliveryPayload($branch, $product, [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1.00, 'modifiers' => []]],
        ]);

        $this->postJson('/api/orders', $payload, $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_modifier_price_tampering_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
        ]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 15.00,
        ]);

        $payload = $this->deliveryPayload($branch, $product, [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 25.00,
                'modifiers' => [['modifier_option_id' => $option->id, 'price_adjustment' => 0.01]],  // tampered
            ]],
        ]);

        $this->postJson('/api/orders', $payload, $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_modifier_from_other_restaurant_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $otherRestaurant = Restaurant::factory()->create(['is_active' => true]);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $otherProduct = $this->product($otherRestaurant);
        $foreignGroup = ModifierGroup::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'product_id' => $otherProduct->id,
        ]);
        $foreignOption = ModifierOption::factory()->create([
            'modifier_group_id' => $foreignGroup->id,
            'price_adjustment' => 0.00,
        ]);

        $payload = $this->deliveryPayload($branch, $product, [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 25.00,
                'modifiers' => [['modifier_option_id' => $foreignOption->id, 'price_adjustment' => 0.00]],
            ]],
        ]);

        $this->postJson('/api/orders', $payload, $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    // ─── Delivery type & payment method validation ───────────────────────────

    public function test_delivery_type_not_allowed_by_restaurant_returns_422(): void
    {
        $restaurant = $this->restaurant(['allows_delivery' => false]);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $this->withDeliveryRange($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_type']);
    }

    public function test_inactive_payment_method_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        // default restaurant() creates cash as active, use terminal which doesn't exist
        $this->postJson('/api/orders', [
            'customer' => ['token' => 'uuid-pm', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'terminal',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []]],
        ], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    // ─── Medium severity: duplicate modifiers & cross-product ────────────────

    public function test_duplicate_modifier_option_in_item_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'selection_type' => 'multiple',
        ]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 5.00,
        ]);

        // Send the same option twice in one item — should be rejected.
        $this->postJson('/api/orders', [
            'customer' => ['token' => 'uuid-dup', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'modifiers' => [
                    ['modifier_option_id' => $option->id, 'price_adjustment' => 5.00],
                    ['modifier_option_id' => $option->id, 'price_adjustment' => 5.00],
                ],
            ]],
        ], $this->authHeaders($restaurant))
            ->assertUnprocessable();
    }

    // ─── Schedule & open/closed validation ──────────────────────────────────

    public function test_order_rejected_when_restaurant_is_closed(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $this->withDeliveryRange($restaurant);

        // Override schedule to mark current day as closed.
        RestaurantSchedule::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('day_of_week', now()->dayOfWeek)
            ->update(['is_closed' => true, 'opens_at' => null, 'closes_at' => null]);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_scheduled_at_outside_operating_hours_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $this->withDeliveryRange($restaurant);

        // Schedule for 03:00 AM next week (same day of week).
        // The restaurant() helper already created a schedule for today's dayOfWeek.
        // Narrow it to 10:00-14:00.
        RestaurantSchedule::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('day_of_week', now()->dayOfWeek)
            ->update(['opens_at' => '10:00', 'closes_at' => '14:00']);

        $scheduledAt = now()->addWeek()->setTime(3, 0);

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product, [
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ]),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_scheduled_at_within_operating_hours_succeeds(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        // Schedule exists from restaurant() helper: 00:00-23:59.
        // Schedule for tomorrow at 12:00 (within hours).
        $tomorrow = now()->addDay()->setTime(12, 0);

        // Ensure schedule for tomorrow's day_of_week.
        RestaurantSchedule::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'day_of_week' => $tomorrow->dayOfWeek],
            ['opens_at' => '00:00', 'closes_at' => '23:59', 'is_closed' => false],
        );

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product, [
                'scheduled_at' => $tomorrow->toIso8601String(),
            ]),
            $this->authHeaders($restaurant),
        )->assertCreated();
    }

    public function test_distance_km_is_computed_server_side(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant, 0, 10, 30.00);

        // Client sends fake distance_km: 0.1, but Google Maps mock returns 1.5 km.
        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product, ['distance_km' => 0.1]),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $order = Order::latest()->first();
        // Server-computed driving distance should be 1.5 km (from Google Maps), NOT 0.1.
        $this->assertGreaterThan(1.0, (float) $order->distance_km);
    }

    // ─── Single-selection cardinality validation ──────────────────────────

    public function test_single_group_with_two_options_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'selection_type' => 'single',
        ]);
        $optionA = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 5.00,
        ]);
        $optionB = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 3.00,
        ]);

        // Send two options for a single-selection group — should be rejected.
        $this->postJson('/api/orders', [
            'customer' => ['token' => 'uuid-single', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'modifiers' => [
                    ['modifier_option_id' => $optionA->id, 'price_adjustment' => 5.00],
                    ['modifier_option_id' => $optionB->id, 'price_adjustment' => 3.00],
                ],
            ]],
        ], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_multiple_group_with_two_options_succeeds(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'selection_type' => 'multiple',
        ]);
        $optionA = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 5.00,
        ]);
        $optionB = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 3.00,
        ]);

        // Two options for a multiple-selection group — should succeed.
        $this->postJson('/api/orders', [
            'customer' => ['token' => 'uuid-multi', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'modifiers' => [
                    ['modifier_option_id' => $optionA->id, 'price_adjustment' => 5.00],
                    ['modifier_option_id' => $optionB->id, 'price_adjustment' => 3.00],
                ],
            ]],
        ], $this->authHeaders($restaurant))
            ->assertCreated();
    }

    public function test_cross_product_modifier_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $productA = $this->product($restaurant, 25.00);
        $productB = $this->product($restaurant, 30.00);

        // Modifier belongs to product A.
        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $productA->id,
        ]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 5.00,
        ]);

        // Send product A's modifier on product B — should be rejected.
        $this->postJson('/api/orders', [
            'customer' => ['token' => 'uuid-cross', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $productB->id,
                'quantity' => 1,
                'unit_price' => $productB->price,
                'modifiers' => [
                    ['modifier_option_id' => $option->id, 'price_adjustment' => 5.00],
                ],
            ]],
        ], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    // ─── Requires Invoice ────────────────────────────────────────────────────

    public function test_order_saves_requires_invoice_true(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product, ['requires_invoice' => true]),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id,
            'requires_invoice' => true,
        ]);
    }

    public function test_order_defaults_requires_invoice_to_false(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id,
            'requires_invoice' => false,
        ]);
    }

    public function test_whatsapp_message_includes_invoice_flag_when_true(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product, ['requires_invoice' => true]),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertStringContainsString('Requiere factura', $response->json('data.whatsapp_message'));
    }

    public function test_whatsapp_message_omits_invoice_flag_when_false(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product, ['requires_invoice' => false]),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertStringNotContainsString('factura', $response->json('data.whatsapp_message'));
    }

    // ─── WhatsApp message format per delivery type ──────────────────────────

    public function test_whatsapp_delivery_message_includes_address_and_branch(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant, 0, 10, 30.00);

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('🚗 Tipo: A domicilio', $message);
        $this->assertStringContainsString('📍 Dirección:', $message);
        $this->assertStringContainsString('🏪 Sucursal:', $message);
        $this->assertStringContainsString('📏 Distancia:', $message);
        $this->assertStringContainsString('📌 Ubicación: https://maps.google.com/?q=', $message);
        $this->assertStringContainsString('Envío:', $message);
    }

    public function test_whatsapp_pickup_message_omits_address_and_delivery_cost(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product, ['delivery_type' => 'pickup']),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('🏪 Tipo: Recoger en sucursal', $message);
        $this->assertStringContainsString('🏪 Sucursal:', $message);
        $this->assertStringNotContainsString('Dirección:', $message);
        $this->assertStringNotContainsString('Distancia:', $message);
        $this->assertStringNotContainsString('Ubicación:', $message);
        $this->assertStringNotContainsString('Envío:', $message);
    }

    public function test_whatsapp_dine_in_message_minimal(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product, ['delivery_type' => 'dine_in']),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('🍽 Tipo: Comer en restaurante', $message);
        $this->assertStringNotContainsString('Dirección:', $message);
        $this->assertStringNotContainsString('Sucursal:', $message);
        $this->assertStringNotContainsString('Distancia:', $message);
        $this->assertStringNotContainsString('Envío:', $message);
    }

    public function test_whatsapp_message_with_modifiers(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 50.00);
        $product->update(['name' => 'Hamburguesa']);
        $this->withDeliveryRange($restaurant);

        $group = ModifierGroup::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'Extras',
            'selection_type' => 'multiple',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $option = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Queso Extra',
            'price_adjustment' => 15.00,
            'production_cost' => 5.00,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/api/orders', $this->deliveryPayload($branch, $product, [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
                'modifiers' => [['modifier_option_id' => $option->id, 'price_adjustment' => 15.00]],
            ]],
        ]), $this->authHeaders($restaurant))->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('Hamburguesa', $message);
        $this->assertStringContainsString('↳ Queso Extra (+$15.00)', $message);
        $this->assertStringContainsString('$65.00', $message); // 50 + 15
    }

    public function test_whatsapp_message_with_scheduled_at(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        // Schedule for tomorrow at noon to avoid timezone/midnight edge cases.
        $scheduledAt = now()->addDay()->setTime(12, 0)->startOfMinute();
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => $scheduledAt->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $response = $this->postJson('/api/orders', $this->deliveryPayload($branch, $product, [
            'scheduled_at' => $scheduledAt->toISOString(),
        ]), $this->authHeaders($restaurant))->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('🕐 Programado para:', $message);
    }

    public function test_whatsapp_message_with_cash_payment(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $response = $this->postJson('/api/orders', $this->deliveryPayload($branch, $product, [
            'cash_amount' => 100.00,
        ]), $this->authHeaders($restaurant))->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('💳 Pago: Efectivo', $message);
        $this->assertStringContainsString('💵 Paga con: $100.00', $message);
    }

    public function test_whatsapp_message_modifier_without_price_omits_adjustment(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 50.00);
        $this->withDeliveryRange($restaurant);

        $group = ModifierGroup::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'Salsa',
            'selection_type' => 'single',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $option = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Salsa Verde',
            'price_adjustment' => 0.00,
            'production_cost' => 0.00,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/api/orders', $this->deliveryPayload($branch, $product, [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
                'modifiers' => [['modifier_option_id' => $option->id, 'price_adjustment' => 0.00]],
            ]],
        ]), $this->authHeaders($restaurant))->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('↳ Salsa Verde', $message);
        $this->assertStringNotContainsString('↳ Salsa Verde (+', $message);
    }

    public function test_whatsapp_message_includes_item_notes(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);
        $this->withDeliveryRange($restaurant);

        $response = $this->postJson('/api/orders', $this->deliveryPayload($branch, $product, [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 25.00,
                'notes' => 'Sin cebolla',
                'modifiers' => [],
            ]],
        ]), $this->authHeaders($restaurant))->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('📝 Sin cebolla', $message);
    }
}
