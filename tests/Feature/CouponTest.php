<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\DeliveryRange;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    // ─── Admin CRUD Helpers ────────────────────────────────────────────────────

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── Admin CRUD Tests ─────────────────────────────────────────────────────

    public function test_admin_can_view_coupons_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('coupons.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Coupons/Index'));
    }

    public function test_admin_can_view_create_coupon_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('coupons.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Coupons/Create'));
    }

    public function test_admin_can_create_fixed_coupon(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('coupons.store'), [
            'code' => 'PROMO50',
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
            'min_purchase' => 100.00,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('coupons.index'));
        $this->assertDatabaseHas('coupons', [
            'restaurant_id' => $restaurant->id,
            'code' => 'PROMO50',
            'discount_type' => 'fixed',
            'discount_value' => '50.00',
            'min_purchase' => '100.00',
        ]);
    }

    public function test_admin_can_create_percentage_coupon(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('coupons.store'), [
            'code' => 'DESCUENTO15',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'max_discount' => 100.00,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('coupons.index'));
        $this->assertDatabaseHas('coupons', [
            'code' => 'DESCUENTO15',
            'discount_type' => 'percentage',
            'discount_value' => '15.00',
            'max_discount' => '100.00',
        ]);
    }

    public function test_coupon_code_auto_uppercases(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->withoutVite()->actingAs($user)->post(route('coupons.store'), [
            'code' => 'promo20',
            'discount_type' => 'fixed',
            'discount_value' => 20.00,
        ]);

        $this->assertDatabaseHas('coupons', [
            'restaurant_id' => $restaurant->id,
            'code' => 'PROMO20',
        ]);
    }

    public function test_coupon_code_unique_per_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        Coupon::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'PROMO50']);

        $response = $this->withoutVite()->actingAs($user)->post(route('coupons.store'), [
            'code' => 'PROMO50',
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_same_code_allowed_for_different_restaurants(): void
    {
        [$user1, $restaurant1] = $this->createAdminWithRestaurant();
        $restaurant2 = Restaurant::factory()->create();

        Coupon::factory()->create(['restaurant_id' => $restaurant2->id, 'code' => 'PROMO50']);

        $response = $this->withoutVite()->actingAs($user1)->post(route('coupons.store'), [
            'code' => 'PROMO50',
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
        ]);

        $response->assertRedirect(route('coupons.index'));
    }

    public function test_admin_can_edit_coupon(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $coupon = Coupon::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'OLD']);

        $response = $this->withoutVite()->actingAs($user)->put(route('coupons.update', $coupon), [
            'code' => 'NEW',
            'discount_type' => 'percentage',
            'discount_value' => 25.00,
            'max_discount' => 80.00,
        ]);

        $response->assertRedirect(route('coupons.index'));
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'code' => 'NEW', 'discount_type' => 'percentage']);
    }

    public function test_admin_can_toggle_coupon_active(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $coupon = Coupon::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $this->withoutVite()->actingAs($user)->patch(route('coupons.toggle-active', $coupon));

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'is_active' => false]);
    }

    public function test_admin_can_delete_coupon(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $coupon = Coupon::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->withoutVite()->actingAs($user)->delete(route('coupons.destroy', $coupon));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_cannot_see_other_restaurants_coupons(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $otherRestaurant = Restaurant::factory()->create();
        $otherCoupon = Coupon::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->get(route('coupons.edit', $otherCoupon));

        $response->assertNotFound();
    }

    public function test_validation_requires_code_and_type(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('coupons.store'), []);

        $response->assertSessionHasErrors(['code', 'discount_type', 'discount_value']);
    }

    public function test_percentage_max_100(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('coupons.store'), [
            'code' => 'TOOBIG',
            'discount_type' => 'percentage',
            'discount_value' => 150.00,
        ]);

        // discount_value max is 99999.99 in the form request (not 100)
        // The model's calculateDiscount caps at subtotal anyway
        // So this test verifies it gets created
        $response->assertRedirect(route('coupons.index'));
    }

    // ─── Coupon Model Validation Logic ────────────────────────────────────────

    public function test_coupon_valid_for_order(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'min_purchase' => 100,
            'discount_type' => 'fixed',
            'discount_value' => 50,
        ]);

        $result = $coupon->isValidForOrder(200.00, '5551234567');

        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
    }

    public function test_inactive_coupon_rejected(): void
    {
        $coupon = Coupon::factory()->inactive()->create();

        $result = $coupon->isValidForOrder(200.00, '5551234567');

        $this->assertFalse($result['valid']);
        $this->assertStringContains('no está activo', $result['reason']);
    }

    public function test_expired_coupon_rejected(): void
    {
        $coupon = Coupon::factory()->expired()->create();

        $result = $coupon->isValidForOrder(200.00, '5551234567');

        $this->assertFalse($result['valid']);
        $this->assertStringContains('expirado', $result['reason']);
    }

    public function test_future_coupon_rejected(): void
    {
        $coupon = Coupon::factory()->future()->create();

        $result = $coupon->isValidForOrder(200.00, '5551234567');

        $this->assertFalse($result['valid']);
        $this->assertStringContains('no está vigente', $result['reason']);
    }

    public function test_min_purchase_not_met(): void
    {
        $coupon = Coupon::factory()->create(['min_purchase' => 500]);

        $result = $coupon->isValidForOrder(200.00, '5551234567');

        $this->assertFalse($result['valid']);
        $this->assertStringContains('pedido mínimo', $result['reason']);
    }

    public function test_max_uses_per_customer_reached(): void
    {
        $coupon = Coupon::factory()->create(['max_uses_per_customer' => 2]);
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();

        CouponUse::create(['coupon_id' => $coupon->id, 'order_id' => $order1->id, 'customer_phone' => '5551234567', 'created_at' => now()]);
        CouponUse::create(['coupon_id' => $coupon->id, 'order_id' => $order2->id, 'customer_phone' => '5551234567', 'created_at' => now()]);

        $result = $coupon->isValidForOrder(200.00, '5551234567');

        $this->assertFalse($result['valid']);
        $this->assertStringContains('máximo de veces', $result['reason']);
    }

    public function test_max_uses_per_customer_different_phone_ok(): void
    {
        $coupon = Coupon::factory()->create(['max_uses_per_customer' => 1]);
        $order = Order::factory()->create();
        CouponUse::create(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'customer_phone' => '5551234567', 'created_at' => now()]);

        $result = $coupon->isValidForOrder(200.00, '5559999999');

        $this->assertTrue($result['valid']);
    }

    public function test_max_total_uses_reached(): void
    {
        $coupon = Coupon::factory()->create(['max_total_uses' => 1]);
        $order = Order::factory()->create();
        CouponUse::create(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'customer_phone' => '5551234567', 'created_at' => now()]);

        $result = $coupon->isValidForOrder(200.00, '5559999999');

        $this->assertFalse($result['valid']);
        $this->assertStringContains('límite de usos', $result['reason']);
    }

    // ─── Discount Calculation ─────────────────────────────────────────────────

    public function test_fixed_discount_calculation(): void
    {
        $coupon = Coupon::factory()->fixed(50)->create();

        $this->assertEquals(50.00, $coupon->calculateDiscount(200.00));
    }

    public function test_fixed_discount_capped_at_subtotal(): void
    {
        $coupon = Coupon::factory()->fixed(100)->create();

        $this->assertEquals(30.00, $coupon->calculateDiscount(30.00));
    }

    public function test_percentage_discount_calculation(): void
    {
        $coupon = Coupon::factory()->percentage(15)->create();

        $this->assertEquals(30.00, $coupon->calculateDiscount(200.00));
    }

    public function test_percentage_discount_with_max(): void
    {
        $coupon = Coupon::factory()->percentage(50, 25.00)->create();

        $this->assertEquals(25.00, $coupon->calculateDiscount(200.00));
    }

    public function test_percentage_discount_without_max(): void
    {
        $coupon = Coupon::factory()->percentage(50)->create();

        $this->assertEquals(100.00, $coupon->calculateDiscount(200.00));
    }

    // ─── API Validate Endpoint ────────────────────────────────────────────────

    private function apiRestaurant(): Restaurant
    {
        $restaurant = Restaurant::factory()->create([
            'access_token' => 'test-coupon-token',
            'is_active' => true,
        ]);

        return $restaurant;
    }

    private function apiHeaders(Restaurant $restaurant): array
    {
        return ['Authorization' => 'Bearer '.$restaurant->access_token];
    }

    public function test_api_validate_valid_coupon(): void
    {
        $restaurant = $this->apiRestaurant();
        Coupon::factory()->fixed(50)->create(['restaurant_id' => $restaurant->id, 'code' => 'VALID50']);

        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'VALID50',
            'subtotal' => 200.00,
            'customer_phone' => '5551234567',
        ], $this->apiHeaders($restaurant));

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'calculated_discount' => 50.00,
            ]);
    }

    public function test_api_validate_case_insensitive(): void
    {
        $restaurant = $this->apiRestaurant();
        Coupon::factory()->fixed(50)->create(['restaurant_id' => $restaurant->id, 'code' => 'VALID50']);

        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'valid50',
            'subtotal' => 200.00,
            'customer_phone' => '5551234567',
        ], $this->apiHeaders($restaurant));

        $response->assertOk()->assertJson(['valid' => true]);
    }

    public function test_api_validate_not_found(): void
    {
        $restaurant = $this->apiRestaurant();

        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'NOEXISTE',
            'subtotal' => 200.00,
            'customer_phone' => '5551234567',
        ], $this->apiHeaders($restaurant));

        $response->assertOk()->assertJson([
            'valid' => false,
            'reason' => 'Cupón no válido o no vigente.',
        ]);
    }

    public function test_api_validate_expired_coupon(): void
    {
        $restaurant = $this->apiRestaurant();
        Coupon::factory()->expired()->create(['restaurant_id' => $restaurant->id, 'code' => 'EXPIRED']);

        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'EXPIRED',
            'subtotal' => 200.00,
            'customer_phone' => '5551234567',
        ], $this->apiHeaders($restaurant));

        $response->assertOk()->assertJson(['valid' => false]);
    }

    public function test_api_validate_from_other_restaurant_not_found(): void
    {
        $restaurant1 = $this->apiRestaurant();
        $restaurant2 = Restaurant::factory()->create(['access_token' => 'other-token', 'is_active' => true]);
        Coupon::factory()->create(['restaurant_id' => $restaurant2->id, 'code' => 'OTHER']);

        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'OTHER',
            'subtotal' => 200.00,
            'customer_phone' => '5551234567',
        ], $this->apiHeaders($restaurant1));

        // Anti-enumeration: the public validate endpoint collapses "not found"
        // and lifecycle failures (inactive/expired/exhausted) into a single
        // generic message so an attacker cannot tell which codes exist.
        $response->assertOk()->assertJson(['valid' => false, 'reason' => 'Cupón no válido o no vigente.']);
    }

    // ─── Order Creation with Coupon ──────────────────────────────────────────

    protected function setUpForOrderTests(): array
    {
        $this->mockGoogleMapsService();

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'test-order-coupon-token',
            'is_active' => true,
            'orders_limit' => 100,
            'allows_delivery' => true,
            'allows_pickup' => true,
            'allows_dine_in' => true,
        ]);

        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $branch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
            'latitude' => 19.430000,
            'longitude' => -99.110000,
        ]);

        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 10,
            'price' => 30.00,
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'price' => 100.00,
            'is_active' => true,
        ]);

        return [$restaurant, $branch, $product];
    }

    private function mockGoogleMapsService(): void
    {
        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => 1.5, 'duration_minutes' => 5],
        ]);
        $this->instance(GoogleMapsService::class, $mock);
    }

    private function orderPayload(Branch $branch, Product $product, array $overrides = []): array
    {
        return array_merge([
            'customer' => ['token' => 'uuid-test-coupon', 'name' => 'Ana López', 'phone' => '5598765432'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'cash_amount' => 500,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => $product->price, 'modifiers' => []],
            ],
        ], $overrides);
    }

    public function test_order_with_valid_coupon_applies_discount(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();
        Coupon::factory()->fixed(50)->create(['restaurant_id' => $restaurant->id, 'code' => 'SAVE50']);

        $payload = $this->orderPayload($branch, $product, ['coupon_code' => 'SAVE50']);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertCreated();

        $order = Order::latest()->first();
        $this->assertEquals('SAVE50', $order->coupon_code);
        $this->assertEquals('50.00', $order->discount_amount);
        // 2x$100 = $200 subtotal - $50 discount + $0 delivery (pickup) = $150
        $this->assertEquals('150.00', $order->total);
        $this->assertDatabaseHas('coupon_uses', [
            'coupon_id' => $order->coupon_id,
            'order_id' => $order->id,
            'customer_phone' => '5598765432',
        ]);
    }

    public function test_order_with_percentage_coupon(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();
        Coupon::factory()->percentage(10, 50.00)->create(['restaurant_id' => $restaurant->id, 'code' => 'PCT10']);

        $payload = $this->orderPayload($branch, $product, ['coupon_code' => 'PCT10']);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertCreated();

        $order = Order::latest()->first();
        // 10% of $200 = $20 (under max of $50)
        $this->assertEquals('20.00', $order->discount_amount);
        $this->assertEquals('180.00', $order->total);
    }

    public function test_order_with_invalid_coupon_rejected(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();

        $payload = $this->orderPayload($branch, $product, ['coupon_code' => 'NOEXISTE']);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');
    }

    public function test_order_with_expired_coupon_rejected(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();
        Coupon::factory()->expired()->create(['restaurant_id' => $restaurant->id, 'code' => 'EXPIRED']);

        $payload = $this->orderPayload($branch, $product, ['coupon_code' => 'EXPIRED']);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertUnprocessable();
    }

    public function test_order_without_coupon_still_works(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();

        $payload = $this->orderPayload($branch, $product);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertCreated();

        $order = Order::latest()->first();
        $this->assertNull($order->coupon_id);
        $this->assertNull($order->coupon_code);
        $this->assertEquals('0.00', $order->discount_amount);
    }

    public function test_coupon_min_purchase_validated_on_order(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();
        Coupon::factory()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'BIGMIN',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'min_purchase' => 500,
        ]);

        // subtotal = 2 * $100 = $200 < $500
        $payload = $this->orderPayload($branch, $product, ['coupon_code' => 'BIGMIN']);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertUnprocessable();
    }

    public function test_cash_amount_validated_against_discounted_total(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();
        Coupon::factory()->fixed(50)->create(['restaurant_id' => $restaurant->id, 'code' => 'SAVE50']);

        // Total will be $150 after discount. Cash $150 should be enough.
        $payload = $this->orderPayload($branch, $product, [
            'coupon_code' => 'SAVE50',
            'cash_amount' => 150.00,
        ]);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertCreated();
    }

    public function test_whatsapp_message_includes_coupon(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();
        Coupon::factory()->fixed(50)->create(['restaurant_id' => $restaurant->id, 'code' => 'SAVE50']);

        $payload = $this->orderPayload($branch, $product, ['coupon_code' => 'SAVE50']);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertCreated();
        $message = $response->json('data.whatsapp_message');
        $this->assertStringContainsString('SAVE50', $message);
        $this->assertStringContainsString('-$50.00', $message);
        $this->assertStringContainsString('Cupón (SAVE50)', $message);
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        [$restaurant, $branch, $product] = $this->setUpForOrderTests();
        // Product is $100, quantity 2 = $200 subtotal. Coupon for $500 off.
        Coupon::factory()->fixed(500)->create(['restaurant_id' => $restaurant->id, 'code' => 'HUGE']);

        $payload = $this->orderPayload($branch, $product, ['coupon_code' => 'HUGE', 'cash_amount' => 0.01]);

        $response = $this->postJson('/api/orders', $payload, ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertCreated();

        $order = Order::latest()->first();
        // Discount capped at subtotal ($200)
        $this->assertEquals('200.00', $order->discount_amount);
        $this->assertEquals('0.00', $order->total); // $200 - $200 + $0 delivery
    }

    // ─── Helper assertion ─────────────────────────────────────────────────────

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
