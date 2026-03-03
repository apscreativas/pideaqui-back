<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(array $attributes = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'access_token' => 'test-token-123',
            'is_active' => true,
        ], $attributes));
    }

    private function authHeaders(Restaurant $restaurant): array
    {
        return ['Authorization' => 'Bearer '.$restaurant->access_token];
    }

    // ─── Auth middleware ──────────────────────────────────────────────────────

    public function test_requests_without_token_return_401(): void
    {
        $this->getJson('/api/restaurant')->assertUnauthorized();
    }

    public function test_requests_with_invalid_token_return_401(): void
    {
        $this->getJson('/api/restaurant', ['Authorization' => 'Bearer invalid-token'])
            ->assertUnauthorized();
    }

    public function test_requests_with_inactive_restaurant_return_401(): void
    {
        $restaurant = $this->restaurant(['is_active' => false]);

        $this->getJson('/api/restaurant', $this->authHeaders($restaurant))
            ->assertUnauthorized();
    }

    // ─── GET /api/restaurant ─────────────────────────────────────────────────

    public function test_get_restaurant_returns_correct_structure(): void
    {
        $restaurant = $this->restaurant();

        PaymentMethod::factory()->create([
            'restaurant_id' => $restaurant->id,
            'type' => 'cash',
            'is_active' => true,
        ]);

        PaymentMethod::factory()->create([
            'restaurant_id' => $restaurant->id,
            'type' => 'transfer',
            'is_active' => false,
        ]);

        $this->getJson('/api/restaurant', $this->authHeaders($restaurant))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'logo_url',
                    'slug',
                    'delivery_methods' => ['delivery', 'pickup', 'dine_in'],
                    'payment_methods',
                    'orders_limit_reached',
                ],
            ])
            ->assertJsonPath('data.orders_limit_reached', false)
            ->assertJsonCount(1, 'data.payment_methods');
    }

    public function test_get_restaurant_reports_orders_limit_reached(): void
    {
        $restaurant = $this->restaurant(['orders_limit' => 2]);
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $customer = Customer::factory()->create();

        $orderData = [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'delivery_type' => 'pickup',
            'status' => 'received',
            'subtotal' => 100,
            'delivery_cost' => 0,
            'total' => 100,
            'payment_method' => 'cash',
        ];

        $restaurant->orders()->createMany([$orderData, $orderData]);

        $this->getJson('/api/restaurant', $this->authHeaders($restaurant))
            ->assertOk()
            ->assertJsonPath('data.orders_limit_reached', true);
    }

    // ─── GET /api/menu ───────────────────────────────────────────────────────

    public function test_get_menu_returns_only_active_categories_and_products(): void
    {
        $restaurant = $this->restaurant();

        $activeCategory = Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => false,
        ]);

        Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $activeCategory->id,
            'is_active' => true,
        ]);
        $inactiveProduct = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $activeCategory->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/menu', $this->authHeaders($restaurant))->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($activeCategory->id, $data[0]['id']);
        $this->assertCount(1, $data[0]['products']);
        $this->assertNotEquals($inactiveProduct->id, $data[0]['products'][0]['id']);
    }

    public function test_get_menu_does_not_expose_production_cost(): void
    {
        $restaurant = $this->restaurant();
        $category = Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
            'production_cost' => 50.00,
        ]);

        $response = $this->getJson('/api/menu', $this->authHeaders($restaurant))->assertOk();

        $product = $response->json('data.0.products.0');
        $this->assertArrayNotHasKey('production_cost', $product);
    }

    public function test_get_menu_includes_modifier_groups_and_options(): void
    {
        $restaurant = $this->restaurant();
        $category = Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
        ]);
        ModifierOption::factory()->create(['modifier_group_id' => $group->id]);

        $response = $this->getJson('/api/menu', $this->authHeaders($restaurant))->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'products' => [
                        '*' => [
                            'modifier_groups' => [
                                '*' => ['id', 'name', 'selection_type', 'is_required', 'options'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_get_menu_does_not_return_other_restaurants_data(): void
    {
        $restaurant = $this->restaurant();
        $otherRestaurant = Restaurant::factory()->create(['is_active' => true]);

        $otherCategory = Category::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/menu', $this->authHeaders($restaurant))->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertNotContains($otherCategory->id, $ids);
    }

    // ─── GET /api/branches ───────────────────────────────────────────────────

    public function test_get_branches_returns_only_active_branches(): void
    {
        $restaurant = $this->restaurant();

        $activeBranch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/branches', $this->authHeaders($restaurant))->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($activeBranch->id, $data[0]['id']);
    }

    public function test_get_branches_includes_schedules(): void
    {
        $restaurant = $this->restaurant();
        $branch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        BranchSchedule::factory()->create([
            'branch_id' => $branch->id,
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
            'is_closed' => false,
        ]);

        $this->getJson('/api/branches', $this->authHeaders($restaurant))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'address', 'latitude', 'longitude', 'whatsapp',
                        'schedules' => [
                            '*' => ['day_of_week', 'opens_at', 'closes_at', 'is_closed'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_get_branches_does_not_return_other_restaurants_branches(): void
    {
        $restaurant = $this->restaurant();
        $otherRestaurant = Restaurant::factory()->create(['is_active' => true]);

        Branch::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/branches', $this->authHeaders($restaurant))->assertOk();

        $this->assertCount(0, $response->json('data'));
    }
}
