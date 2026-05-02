<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Restaurant, Branch} */
    private function setupRestaurant(string $role = 'admin'): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500]);
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => $role,
        ]);

        if ($role === 'operator') {
            $user->branches()->attach($branch->id);
        }

        return [$user, $restaurant, $branch];
    }

    private function makeOrder(Restaurant $restaurant, Branch $branch, array $overrides = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => Customer::factory()->create()->id,
            'subtotal' => 200.00,
            'total' => 200.00,
            'discount_amount' => 0,
        ], $overrides));

        // 1 item con costo 80 → utilidad = 200 - 80 = 120
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => 'Test Burger',
            'unit_price' => 200.00,
            'production_cost' => 80.00,
            'quantity' => 1,
        ]);

        return $order;
    }

    public function test_unauthenticated_redirected(): void
    {
        $this->get(route('orders.history'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_history_with_default_7_day_range(): void
    {
        [$user] = $this->setupRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.history'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/History')
            ->has('orders')
            ->has('summary')
            ->has('branches')
            ->has('filters.from')
            ->has('filters.to')
            ->where('filters.status', 'all')
        );
    }

    public function test_operator_can_view_history(): void
    {
        [$user] = $this->setupRestaurant('operator');

        $this->withoutVite()->actingAs($user)->get(route('orders.history'))->assertStatus(200);
    }

    public function test_summary_aggregates_full_filtered_set_not_just_page(): void
    {
        [$user, $restaurant, $branch] = $this->setupRestaurant();

        // 3 pedidos delivered: cada uno total=200, costo=80, utilidad=120
        for ($i = 0; $i < 3; $i++) {
            $this->makeOrder($restaurant, $branch, ['status' => 'delivered']);
        }

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.history'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.count', 3)
            ->where('summary.sum_total', 600)
            ->where('summary.sum_cost', 240)
            ->where('summary.sum_profit', 360)
        );
    }

    public function test_status_filter_delivered_excludes_cancelled(): void
    {
        [$user, $restaurant, $branch] = $this->setupRestaurant();

        $this->makeOrder($restaurant, $branch, ['status' => 'delivered']);
        $this->makeOrder($restaurant, $branch, ['status' => 'cancelled', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.history', ['status' => 'delivered']));

        $response->assertInertia(fn ($page) => $page->where('summary.count', 1));
    }

    public function test_status_filter_cancelled_only(): void
    {
        [$user, $restaurant, $branch] = $this->setupRestaurant();

        $this->makeOrder($restaurant, $branch, ['status' => 'delivered']);
        $this->makeOrder($restaurant, $branch, ['status' => 'cancelled', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.history', ['status' => 'cancelled']));

        $response->assertInertia(fn ($page) => $page->where('summary.count', 1));
    }

    public function test_branch_filter_scopes_results(): void
    {
        [$user, $restaurant] = $this->setupRestaurant();
        $branchA = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $branchB = Branch::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->makeOrder($restaurant, $branchA, ['status' => 'delivered']);
        $this->makeOrder($restaurant, $branchB, ['status' => 'delivered']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.history', ['branch_id' => $branchA->id]));

        $response->assertInertia(fn ($page) => $page->where('summary.count', 1));
    }

    public function test_other_restaurant_orders_not_visible(): void
    {
        [$user, $restaurant, $branch] = $this->setupRestaurant();
        $other = Restaurant::factory()->create(['orders_limit' => 500]);
        $otherBranch = Branch::factory()->create(['restaurant_id' => $other->id]);

        $this->makeOrder($restaurant, $branch, ['status' => 'delivered']);
        $this->makeOrder($other, $otherBranch, ['status' => 'delivered']);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.history'));

        $response->assertInertia(fn ($page) => $page->where('summary.count', 1));
    }

    public function test_modifier_costs_included_in_summary(): void
    {
        [$user, $restaurant, $branch] = $this->setupRestaurant();

        $order = $this->makeOrder($restaurant, $branch, ['status' => 'delivered']);
        OrderItemModifier::create([
            'order_item_id' => $order->items()->first()->id,
            'modifier_option_id' => null,
            'modifier_option_name' => 'Extra cheese',
            'price_adjustment' => 0,
            'production_cost' => 15.00,
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('orders.history'));

        // costo = 80 (item) + 15 (modifier) * 1 (qty) = 95
        $response->assertInertia(fn ($page) => $page
            ->where('summary.sum_cost', 95)
            ->where('summary.sum_profit', 105) // 200 - 95
        );
    }
}
