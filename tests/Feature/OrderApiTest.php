<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function restaurant(array $attrs = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'access_token' => 'test-order-token',
            'is_active' => true,
            'max_monthly_orders' => 100,
        ], $attrs));
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
            'customer' => ['token' => 'uuid-test-001', 'name' => 'María García', 'phone' => '+5215598765432'],
            'delivery_type' => 'delivery',
            'branch_id' => $branch->id,
            'address' => 'Calle Morelos 45, Col. Roma Norte',
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
            ->assertJsonValidationErrors(['address', 'latitude', 'longitude', 'distance_km', 'delivery_cost']);
    }

    // ─── Happy paths ─────────────────────────────────────────────────────────

    public function test_create_delivery_order_returns_201_with_correct_structure(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

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

        $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),  // delivery_cost = $30
            $this->authHeaders($restaurant),
        )->assertCreated();

        $order = Order::latest()->first();
        $this->assertEquals('50.00', $order->subtotal);
        $this->assertEquals('30.00', $order->delivery_cost);
        $this->assertEquals('80.00', $order->total);
    }

    public function test_order_with_modifiers_calculates_correctly(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $group = ModifierGroup::factory()->create(['restaurant_id' => $restaurant->id]);
        $product->modifierGroups()->attach($group);
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

        $response = $this->postJson(
            '/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('Taco de Bistec', $message);
        $this->assertStringContainsString('$50.00', $message);  // 2 × $25
        $this->assertStringContainsString('$80.00', $message);  // total
        $this->assertStringContainsString('🛵', $message);
    }

    public function test_order_number_is_zero_padded(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $response = $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertCreated();

        $this->assertMatchesRegularExpression('/^#\d{4,}$/', $response->json('data.order_number'));
    }

    // ─── Business rule failures ───────────────────────────────────────────────

    public function test_monthly_limit_reached_returns_422(): void
    {
        $restaurant = $this->restaurant(['max_monthly_orders' => 1]);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);
        $customer = Customer::factory()->create();

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

    public function test_branch_from_other_restaurant_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $otherRestaurant = Restaurant::factory()->create(['is_active' => true]);
        $foreignBranch = $this->branch($otherRestaurant);
        $product = $this->product($restaurant);

        $this->postJson('/api/orders',
            $this->deliveryPayload($foreignBranch, $product),
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

        $this->postJson('/api/orders',
            $this->deliveryPayload($branch, $product),
            $this->authHeaders($restaurant),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_price_tampering_returns_422(): void
    {
        $restaurant = $this->restaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

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

        $group = ModifierGroup::factory()->create(['restaurant_id' => $restaurant->id]);
        $product->modifierGroups()->attach($group);
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

        $foreignGroup = ModifierGroup::factory()->create(['restaurant_id' => $otherRestaurant->id]);
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
}
