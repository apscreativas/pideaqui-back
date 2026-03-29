<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEditTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);
        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    private function createOrderWithItems(int $restaurantId, array $orderOverrides = []): Order
    {
        $branch = Branch::factory()->create(['restaurant_id' => $restaurantId]);
        $customer = Customer::factory()->create();
        $category = Category::factory()->create(['restaurant_id' => $restaurantId]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurantId,
            'category_id' => $category->id,
            'price' => 100.00,
            'production_cost' => 40.00,
        ]);

        $order = Order::factory()->create(array_merge([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'delivery_type' => 'delivery',
            'status' => 'received',
            'subtotal' => 200.00,
            'delivery_cost' => 50.00,
            'total' => 250.00,
            'payment_method' => 'cash',
            'cash_amount' => 1000.00,
            'address_street' => 'Av Juárez',
            'address_number' => '10',
            'address_colony' => 'Centro',
            'address_references' => 'Junto al parque',
            'latitude' => 19.4326,
            'longitude' => -99.1332,
            'distance_km' => 3.50,
        ], $orderOverrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 100.00,
            'production_cost' => 40.00,
        ]);

        return $order->fresh(['items']);
    }

    // ─── Edit page access ───────────────────────────────────────────────────────

    public function test_admin_can_access_edit_page_for_received_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, ['status' => 'received']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.edit', $order));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Edit')
            ->has('order')
            ->has('categories')
            ->has('promotions')
            ->has('paymentMethods')
        );
    }

    public function test_admin_can_access_edit_page_for_preparing_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, ['status' => 'preparing']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.edit', $order));

        $response->assertStatus(200);
    }

    public function test_admin_cannot_access_edit_page_for_on_the_way_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, ['status' => 'on_the_way']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.edit', $order));

        $response->assertForbidden();
    }

    public function test_admin_cannot_access_edit_page_for_delivered_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, ['status' => 'delivered']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.edit', $order));

        $response->assertForbidden();
    }

    public function test_admin_cannot_access_edit_page_for_cancelled_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, [
            'status' => 'cancelled',
            'cancellation_reason' => 'Test',
            'cancelled_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.edit', $order));

        $response->assertForbidden();
    }

    // ─── Multitenancy ───────────────────────────────────────────────────────────

    public function test_admin_cannot_edit_order_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();
        $other = Restaurant::factory()->create();
        $order = $this->createOrderWithItems($other->id);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.edit', $order));

        $response->assertStatus(404);
    }

    public function test_admin_cannot_update_order_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();
        $other = Restaurant::factory()->create();
        PaymentMethod::factory()->cash()->create(['restaurant_id' => $other->id]);
        $order = $this->createOrderWithItems($other->id);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(404);
    }

    // ─── Item editing ───────────────────────────────────────────────────────────

    public function test_admin_can_change_item_quantity(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();
        $productId = $item->product_id;

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 5,
                ],
            ],
        ]);

        $response->assertRedirect(route('orders.show', $order->id));
        $order->refresh();
        $this->assertEquals(500.00, (float) $order->subtotal);
        $this->assertEquals(550.00, (float) $order->total);
        $this->assertEquals(1, $order->edit_count);
        $this->assertNotNull($order->edited_at);
    }

    public function test_admin_can_add_new_product(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $existingItem = $order->items->first();

        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $newProduct = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'price' => 75.00,
            'production_cost' => 30.00,
        ]);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                ['product_id' => $existingItem->product_id, 'quantity' => 2],
                ['product_id' => $newProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(2, $order->items->count());
        $this->assertEquals(275.00, (float) $order->subtotal);
    }

    public function test_admin_can_remove_item(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $existingItem = $order->items->first();

        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $newProduct = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'price' => 60.00,
            'production_cost' => 25.00,
        ]);

        // Add second item first
        $order->items()->create([
            'product_id' => $newProduct->id,
            'product_name' => $newProduct->name,
            'quantity' => 1,
            'unit_price' => 60.00,
            'production_cost' => 25.00,
        ]);
        $order->update(['subtotal' => 260.00, 'total' => 310.00]);

        // Now remove the original item, keep only the new one
        $response = $this->actingAs($user)->put(route('orders.update', $order->fresh()), [
            'expected_updated_at' => $order->fresh()->updated_at->toISOString(),
            'items' => [
                ['product_id' => $newProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(1, $order->items->count());
        $this->assertEquals(60.00, (float) $order->subtotal);
    }

    public function test_cannot_remove_all_items(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [],
        ]);

        $response->assertSessionHasErrors();
    }

    // ─── Snapshot re-capture ────────────────────────────────────────────────────

    public function test_snapshots_are_updated_on_edit(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();
        $product = Product::find($item->product_id);

        // Update product price and name in catalog
        $product->update(['price' => 120.00, 'production_cost' => 50.00, 'name' => 'Updated Name']);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect();
        $order->refresh();
        $updatedItem = $order->items->first();
        $this->assertEquals('Updated Name', $updatedItem->product_name);
        $this->assertEquals(120.00, (float) $updatedItem->unit_price);
        $this->assertEquals(50.00, (float) $updatedItem->production_cost);
        $this->assertEquals(240.00, (float) $order->subtotal);
    }

    // ─── Delivery cost immutability ─────────────────────────────────────────────

    public function test_delivery_cost_not_recalculated_on_item_edit(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();

        $this->assertEquals(50.00, (float) $order->delivery_cost);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                ['product_id' => $item->product_id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(50.00, (float) $order->delivery_cost);
        $this->assertEquals(150.00, (float) $order->total);
    }

    // ─── Address-only edit ──────────────────────────────────────────────────────

    public function test_admin_can_edit_address_without_price_recalculation(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Av Reforma',
            'address_number' => '500',
            'address_colony' => 'Juárez',
            'address_references' => 'Frente al Ángel',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('Av Reforma', $order->address_street);
        $this->assertEquals('500', $order->address_number);
        $this->assertEquals('Juárez', $order->address_colony);
        $this->assertEquals('Frente al Ángel', $order->address_references);
        // Prices unchanged
        $this->assertEquals(200.00, (float) $order->subtotal);
        $this->assertEquals(250.00, (float) $order->total);
        $this->assertEquals(50.00, (float) $order->delivery_cost);
    }

    public function test_admin_can_edit_gps_coordinates(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'latitude' => 19.5000,
            'longitude' => -99.2000,
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(19.5, (float) $order->latitude);
        $this->assertEquals(-99.2, (float) $order->longitude);
        // Distance unchanged
        $this->assertEquals(3.50, (float) $order->distance_km);
    }

    // ─── Payment method changes ─────────────────────────────────────────────────

    public function test_admin_can_change_payment_method(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        PaymentMethod::factory()->terminal()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        $order = $this->createOrderWithItems($restaurant->id);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'payment_method' => 'terminal',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('terminal', $order->payment_method);
        $this->assertNull($order->cash_amount);
    }

    public function test_cannot_change_to_inactive_payment_method(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        PaymentMethod::factory()->transfer()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => false,
        ]);
        $order = $this->createOrderWithItems($restaurant->id);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'payment_method' => 'transfer',
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    // ─── Audit trail ────────────────────────────────────────────────────────────

    public function test_audit_trail_created_on_item_edit(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();

        $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                ['product_id' => $item->product_id, 'quantity' => 5],
            ],
            'reason' => 'Cliente pidió más',
        ]);

        $this->assertDatabaseHas('order_audits', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'action' => 'items_modified',
            'reason' => 'Cliente pidió más',
        ]);
    }

    public function test_audit_trail_created_on_address_edit(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);

        $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Calle Nueva',
        ]);

        $this->assertDatabaseHas('order_audits', [
            'order_id' => $order->id,
            'action' => 'address_modified',
        ]);
    }

    public function test_audit_trail_records_old_and_new_total(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();

        $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                ['product_id' => $item->product_id, 'quantity' => 1],
            ],
        ]);

        $audit = $order->audits()->first();
        $this->assertEquals(250.00, (float) $audit->old_total);
        $this->assertEquals(150.00, (float) $audit->new_total);
    }

    // ─── Optimistic locking ─────────────────────────────────────────────────────

    public function test_returns_conflict_when_order_was_modified(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $staleTimestamp = $order->updated_at->toISOString();

        // Simulate another user modifying the order — ensure timestamp differs
        $this->travel(5)->seconds();
        $order->update(['subtotal' => 999.99, 'total' => 1049.99]);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $staleTimestamp,
            'address_street' => 'Changed Street',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        // Address should NOT have changed
        $order->refresh();
        $this->assertEquals('Av Juárez', $order->address_street);
    }

    // ─── edit_count and edited_at ───────────────────────────────────────────────

    public function test_edit_count_increments_on_each_edit(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();

        $this->assertEquals(0, $order->edit_count);

        // First edit
        $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Edit 1',
        ]);
        $order->refresh();
        $this->assertEquals(1, $order->edit_count);
        $this->assertNotNull($order->edited_at);

        // Second edit
        $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Edit 2',
        ]);
        $order->refresh();
        $this->assertEquals(2, $order->edit_count);
    }

    // ─── Status restrictions on update ──────────────────────────────────────────

    public function test_cannot_update_on_the_way_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, ['status' => 'on_the_way']);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Should fail',
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_update_delivered_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, ['status' => 'delivered']);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Should fail',
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_update_cancelled_order(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id, [
            'status' => 'cancelled',
            'cancellation_reason' => 'Test',
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Should fail',
        ]);

        $response->assertForbidden();
    }

    // ─── Inactive product validation ────────────────────────────────────────────

    public function test_cannot_add_inactive_product(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $existingItem = $order->items->first();

        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $inactiveProduct = Product::factory()->inactive()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'price' => 80.00,
        ]);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                ['product_id' => $existingItem->product_id, 'quantity' => 2],
                ['product_id' => $inactiveProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('items');
    }

    // ─── No changes validation ──────────────────────────────────────────────────

    public function test_expected_updated_at_is_required(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'address_street' => 'Test',
        ]);

        $response->assertSessionHasErrors('expected_updated_at');
    }

    // ─── Immutable fields ───────────────────────────────────────────────────────

    public function test_delivery_type_branch_and_distance_remain_unchanged(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();

        $response = $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'items' => [
                ['product_id' => $item->product_id, 'quantity' => 3],
            ],
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('delivery', $order->delivery_type);
        $this->assertEquals(3.50, (float) $order->distance_km);
    }

    // ─── Show page has audits ───────────────────────────────────────────────────

    public function test_show_page_includes_audits(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $order = $this->createOrderWithItems($restaurant->id);
        $item = $order->items->first();

        // Make an edit to create an audit
        $this->actingAs($user)->put(route('orders.update', $order), [
            'expected_updated_at' => $order->updated_at->toISOString(),
            'address_street' => 'Changed',
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.show', $order));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Show')
            ->has('order.audits', 1)
        );
    }
}
