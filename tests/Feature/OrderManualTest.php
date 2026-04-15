<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryRange;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => 1.5, 'duration_minutes' => 5],
        ]);
        $this->instance(GoogleMapsService::class, $mock);
    }

    private function setupRestaurant(array $attrs = []): Restaurant
    {
        $restaurant = Restaurant::factory()->create(array_merge([
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

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        return $restaurant;
    }

    private function adminFor(Restaurant $restaurant): User
    {
        return User::factory()->create(['restaurant_id' => $restaurant->id]);
    }

    private function branch(Restaurant $restaurant): Branch
    {
        return Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'whatsapp' => '+5215512345678',
            'is_active' => true,
            'latitude' => 19.430000,
            'longitude' => -99.110000,
        ]);
    }

    private function product(Restaurant $restaurant, float $price = 25.00): Product
    {
        $category = Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        return Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function pickupPayload(Branch $branch, Product $product, array $overrides = []): array
    {
        return array_merge([
            'customer' => ['name' => 'María García', 'phone' => '5598765432'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => $product->price, 'modifiers' => []],
            ],
        ], $overrides);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_create_form(): void
    {
        $this->get(route('orders.create'))->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_post_manual_order(): void
    {
        $this->post(route('orders.store'), [])->assertRedirect(route('login'));
    }

    // ─── Create form ───────────────────────────────────────────────────────────

    public function test_admin_can_view_create_form(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $this->branch($restaurant);

        $response = $this->withoutVite()->actingAs($admin)->get(route('orders.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Create')
            ->has('branches')
            ->has('categories')
            ->has('promotions')
            ->has('paymentMethods')
            ->has('allowsDelivery')
            ->has('orders_limit')
        );
    }

    // ─── Happy paths ───────────────────────────────────────────────────────────

    public function test_admin_can_create_pickup_order(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($restaurant->id, $order->restaurant_id);
        $this->assertEquals($branch->id, $order->branch_id);
        $this->assertEquals('received', $order->status);
        $this->assertEquals('manual', $order->source);
        $this->assertEquals('50.00', $order->subtotal);
        $this->assertEquals('0.00', $order->delivery_cost);
        $this->assertEquals('50.00', $order->total);
    }

    public function test_admin_can_create_dine_in_order(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 50.00);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, ['delivery_type' => 'dine_in']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id,
            'delivery_type' => 'dine_in',
            'source' => 'manual',
        ]);
    }

    public function test_admin_can_create_delivery_order_with_calculated_cost(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 10,
            'price' => 30.00,
        ]);

        $this->actingAs($admin)
            ->post(route('orders.store'), [
                'customer' => ['name' => 'Pedro', 'phone' => '5512345678'],
                'delivery_type' => 'delivery',
                'branch_id' => $branch->id,
                'address_street' => 'Av Insurgentes',
                'address_number' => '100',
                'address_colony' => 'Roma',
                'latitude' => 19.420000,
                'longitude' => -99.110000,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order = Order::latest()->first();
        $this->assertEquals('25.00', $order->subtotal);
        $this->assertEquals('30.00', $order->delivery_cost);
        $this->assertEquals('55.00', $order->total);
    }

    // ─── Source + audit trail ─────────────────────────────────────────────────

    public function test_manual_order_persists_source_and_user_in_event(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertRedirect();

        $order = Order::latest()->first();
        $this->assertEquals('manual', $order->source);

        $event = OrderEvent::where('order_id', $order->id)->where('action', 'created')->first();
        $this->assertNotNull($event);
        $this->assertEquals($admin->id, $event->user_id);
    }

    // ─── Customer lookup by phone ─────────────────────────────────────────────

    public function test_existing_customer_with_same_phone_is_reused(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $existing = Customer::factory()->create(['phone' => '5598765432', 'name' => 'Old Name']);
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $existing->id,
        ]);

        $beforeCount = Customer::count();

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, [
                'customer' => ['name' => 'New Name', 'phone' => '5598765432'],
            ]))
            ->assertRedirect();

        $this->assertEquals($beforeCount, Customer::count());

        $existing->refresh();
        $this->assertEquals('New Name', $existing->name);

        $order = Order::latest()->first();
        $this->assertEquals($existing->id, $order->customer_id);
    }

    public function test_new_customer_created_when_phone_not_seen_at_restaurant(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, [
                'customer' => ['name' => 'Brand New', 'phone' => '5512309876'],
            ]))
            ->assertRedirect();

        $order = Order::latest()->first();
        $this->assertNotNull($order->customer);
        $this->assertEquals('Brand New', $order->customer->name);
        $this->assertEquals('5512309876', $order->customer->phone);
        $this->assertStringStartsWith('manual_', $order->customer->token);
    }

    public function test_existing_customer_from_other_restaurant_is_not_reused(): void
    {
        $restaurant = $this->setupRestaurant();
        $other = Restaurant::factory()->create();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $otherCustomer = Customer::factory()->create(['phone' => '5598765432']);
        Order::factory()->create([
            'restaurant_id' => $other->id,
            'branch_id' => Branch::factory()->create(['restaurant_id' => $other->id])->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertRedirect();

        $order = Order::latest()->first();
        $this->assertNotEquals($otherCustomer->id, $order->customer_id);
    }

    // ─── Limit enforcement ────────────────────────────────────────────────────

    public function test_manual_order_blocked_when_period_limit_reached(): void
    {
        $restaurant = $this->setupRestaurant(['orders_limit' => 1]);
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => Customer::factory()->create()->id,
            'status' => 'received',
        ]);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals(1, Order::where('restaurant_id', $restaurant->id)->count());
    }

    // ─── Anti-tampering ───────────────────────────────────────────────────────

    public function test_manipulated_unit_price_is_rejected(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1.00, 'modifiers' => []],
                ],
            ]))
            ->assertSessionHasErrors('items');

        $this->assertEquals(0, Order::where('restaurant_id', $restaurant->id)->count());
    }

    // ─── Modifier validation ──────────────────────────────────────────────────

    public function test_required_modifier_group_must_have_selection(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant, 25.00);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'is_required' => true,
            'is_active' => true,
        ]);
        ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []],
                ],
            ]))
            ->assertSessionHasErrors('items');

        $this->assertEquals(0, Order::count());
    }

    // ─── Payment method validation ────────────────────────────────────────────

    public function test_inactive_payment_method_is_rejected(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        // No 'transfer' payment method active for this restaurant
        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, [
                'payment_method' => 'transfer',
            ]))
            ->assertSessionHasErrors('payment_method');

        $this->assertEquals(0, Order::count());
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function test_phone_must_be_10_digits(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, [
                'customer' => ['name' => 'Test', 'phone' => '123'],
            ]))
            ->assertSessionHasErrors('customer.phone');
    }

    public function test_at_least_one_item_required(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), [
                'customer' => ['name' => 'Test', 'phone' => '5512345678'],
                'delivery_type' => 'pickup',
                'branch_id' => $branch->id,
                'payment_method' => 'cash',
                'items' => [],
            ])
            ->assertSessionHasErrors('items');
    }

    // ─── Tenant isolation ─────────────────────────────────────────────────────

    public function test_admin_cannot_create_order_using_other_restaurants_branch(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $product = $this->product($restaurant);

        $other = $this->setupRestaurant();
        $otherBranch = $this->branch($other);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($otherBranch, $product))
            ->assertSessionHasErrors('branch_id');

        $this->assertEquals(0, Order::count());
    }

    public function test_admin_cannot_create_order_using_other_restaurants_product(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);

        $other = $this->setupRestaurant();
        $otherProduct = $this->product($other);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $otherProduct))
            ->assertSessionHasErrors('items');

        $this->assertEquals(0, Order::count());
    }

    // ─── Delivery preview endpoint ────────────────────────────────────────────

    public function test_preview_delivery_returns_branch_distance_and_cost_when_in_coverage(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 10,
            'price' => 30.00,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('orders.preview-delivery'), [
                'latitude' => 19.420000,
                'longitude' => -99.110000,
            ]);

        $response->assertOk()
            ->assertJsonPath('in_coverage', true)
            ->assertJsonPath('branch.id', $branch->id);
        $this->assertEquals(30.0, (float) $response->json('delivery_cost'));
    }

    public function test_preview_delivery_reports_out_of_coverage_when_no_range_matches(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $this->branch($restaurant);
        // Range covers 50–100 km, but mock returns 1.5 km → out of coverage.
        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 50,
            'max_km' => 100,
            'price' => 30.00,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('orders.preview-delivery'), [
                'latitude' => 19.420000,
                'longitude' => -99.110000,
            ]);

        $response->assertOk()->assertJsonPath('in_coverage', false);
    }

    public function test_preview_delivery_returns_422_when_no_active_branches(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        // No branch created.

        $this->actingAs($admin)
            ->postJson(route('orders.preview-delivery'), [
                'latitude' => 19.420000,
                'longitude' => -99.110000,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_preview_delivery_requires_authentication(): void
    {
        $this->postJson(route('orders.preview-delivery'), [
            'latitude' => 19.42,
            'longitude' => -99.11,
        ])->assertStatus(401);
    }

    public function test_preview_delivery_validates_coordinates(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);

        $this->actingAs($admin)
            ->postJson(route('orders.preview-delivery'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_preview_delivery_blocked_when_restaurant_does_not_allow_delivery(): void
    {
        $restaurant = $this->setupRestaurant(['allows_delivery' => false]);
        $admin = $this->adminFor($restaurant);
        $this->branch($restaurant);

        $this->actingAs($admin)
            ->postJson(route('orders.preview-delivery'), [
                'latitude' => 19.42,
                'longitude' => -99.11,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    // ─── Limit summary surfaced in Inertia props ──────────────────────────────

    public function test_create_form_exposes_limit_reason_when_period_expired(): void
    {
        $restaurant = $this->setupRestaurant([
            'orders_limit_start' => now()->subDays(35),
            'orders_limit_end' => now()->subDays(5),
        ]);
        $admin = $this->adminFor($restaurant);
        $this->branch($restaurant);

        $response = $this->withoutVite()->actingAs($admin)->get(route('orders.create'));

        $response->assertInertia(fn ($page) => $page
            ->where('limit_reason', 'period_expired')
            ->has('limit_period.end')
        );
    }

    public function test_create_form_exposes_null_reason_when_under_limit(): void
    {
        $restaurant = $this->setupRestaurant(['orders_limit' => 100]);
        $admin = $this->adminFor($restaurant);
        $this->branch($restaurant);

        $response = $this->withoutVite()->actingAs($admin)->get(route('orders.create'));

        $response->assertInertia(fn ($page) => $page
            ->where('limit_reason', null)
            ->where('orders_limit', 100)
        );
    }

    // ─── Yellow-tier: contextual error messages on POST ──────────────────────

    public function test_period_expired_at_submit_shows_contextual_message(): void
    {
        $restaurant = $this->setupRestaurant([
            'orders_limit_start' => now()->subDays(35),
            'orders_limit_end' => now()->subDays(5),
        ]);
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $response = $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product));

        $response->assertRedirect()->assertSessionHas('error');
        $error = session('error');
        $this->assertStringContainsString('expir', strtolower($error));
        $this->assertEquals(0, Order::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_no_active_branches_for_delivery_returns_friendly_error(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $product = $this->product($restaurant);
        // No branch created.

        $response = $this->actingAs($admin)
            ->post(route('orders.store'), [
                'customer' => ['name' => 'Test', 'phone' => '5512345678'],
                'delivery_type' => 'delivery',
                'branch_id' => 999,
                'address_street' => 'X',
                'address_number' => '1',
                'address_colony' => 'X',
                'latitude' => 19.42,
                'longitude' => -99.11,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []],
                ],
            ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertEquals(0, Order::count());
    }

    // ─── Yellow-tier: operator branch authorization ──────────────────────────

    public function test_operator_cannot_create_pickup_order_at_unassigned_branch(): void
    {
        $restaurant = $this->setupRestaurant();
        $branch = $this->branch($restaurant);
        $forbiddenBranch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        $product = $this->product($restaurant);

        $operator = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'operator',
        ]);
        $operator->branches()->attach($branch->id);

        $this->actingAs($operator)
            ->post(route('orders.store'), $this->pickupPayload($forbiddenBranch, $product))
            ->assertSessionHasErrors('branch_id');

        $this->assertEquals(0, Order::count());
    }

    public function test_operator_can_create_pickup_order_at_their_own_branch(): void
    {
        $restaurant = $this->setupRestaurant();
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $operator = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'operator',
        ]);
        $operator->branches()->attach($branch->id);

        $this->actingAs($operator)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertSessionHas('success');

        $this->assertEquals(1, Order::count());
    }

    public function test_create_form_only_lists_operator_assigned_branches(): void
    {
        $restaurant = $this->setupRestaurant();
        $assigned = $this->branch($restaurant);
        Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $operator = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'operator',
        ]);
        $operator->branches()->attach($assigned->id);

        $response = $this->withoutVite()->actingAs($operator)->get(route('orders.create'));

        $response->assertInertia(fn ($page) => $page->has('branches', 1));
    }

    // ─── Yellow-tier: business rules ─────────────────────────────────────────

    public function test_restaurant_closed_blocks_immediate_order(): void
    {
        $restaurant = Restaurant::factory()->create([
            'is_active' => true,
            'orders_limit' => 100,
            'allows_delivery' => true,
            'allows_pickup' => true,
            'allows_dine_in' => true,
        ]);
        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        // Schedule for today is closed.
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);

        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertSessionHasErrors('scheduled_at');

        $this->assertEquals(0, Order::count());
    }

    public function test_allows_delivery_false_rejects_delivery_order(): void
    {
        $restaurant = $this->setupRestaurant(['allows_delivery' => false]);
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), [
                'customer' => ['name' => 'X', 'phone' => '5512345678'],
                'delivery_type' => 'delivery',
                'branch_id' => $branch->id,
                'address_street' => 'X',
                'address_number' => '1',
                'address_colony' => 'X',
                'latitude' => 19.42,
                'longitude' => -99.11,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'modifiers' => []],
                ],
            ])
            ->assertSessionHasErrors('delivery_type');

        $this->assertEquals(0, Order::count());
    }

    public function test_requires_invoice_is_persisted(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product, ['requires_invoice' => true]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id,
            'requires_invoice' => true,
        ]);
    }

    // ─── Yellow-tier: cancellation of manual order ───────────────────────────

    public function test_manual_order_can_be_cancelled_like_any_other(): void
    {
        $restaurant = $this->setupRestaurant();
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertSessionHas('success');

        $order = Order::latest()->first();

        $this->actingAs($admin)
            ->put(route('orders.cancel', $order), ['cancellation_reason' => 'Cliente desistió'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'source' => 'manual',
        ]);
    }

    // ─── Yellow-tier: subscription mode at limit ─────────────────────────────

    public function test_subscription_mode_at_plan_limit_blocks_manual_order(): void
    {
        $plan = Plan::factory()->create(['orders_limit' => 1]);
        $restaurant = Restaurant::factory()->subscription()->create([
            'plan_id' => $plan->id,
            'is_active' => true,
            'allows_delivery' => true,
            'allows_pickup' => true,
            'allows_dine_in' => true,
            'orders_limit' => 999, // legacy column — must NOT be considered
        ]);
        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        // Pre-existing order in current calendar month uses the plan limit.
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => Customer::factory()->create()->id,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals(1, Order::where('restaurant_id', $restaurant->id)->count());
    }

    // ─── Operational gate (billing status / period) ───────────────────────────

    public function test_manual_order_blocked_when_restaurant_suspended(): void
    {
        $restaurant = $this->setupRestaurant(['status' => 'suspended', 'is_active' => false]);
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $response = $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product));

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($msg) => str_contains($msg, 'suspendido'));
        $this->assertEquals(0, Order::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_manual_order_blocked_when_period_expired(): void
    {
        $restaurant = $this->setupRestaurant([
            'orders_limit_start' => now()->subDays(30),
            'orders_limit_end' => now()->subDays(1),
        ]);
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $response = $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product));

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($msg) => str_contains(strtolower($msg), 'expir'));
        $this->assertEquals(0, Order::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_manual_order_blocked_when_past_due(): void
    {
        $restaurant = $this->setupRestaurant(['status' => 'past_due']);
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $response = $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product));

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($msg) => str_contains($msg, 'cobro falló'));
        $this->assertEquals(0, Order::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_manual_order_allowed_when_grace_period_active(): void
    {
        $restaurant = $this->setupRestaurant([
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(7),
        ]);
        $admin = $this->adminFor($restaurant);
        $branch = $this->branch($restaurant);
        $product = $this->product($restaurant);

        $this->actingAs($admin)
            ->post(route('orders.store'), $this->pickupPayload($branch, $product))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        $this->assertEquals(1, Order::where('restaurant_id', $restaurant->id)->count());
    }
}
