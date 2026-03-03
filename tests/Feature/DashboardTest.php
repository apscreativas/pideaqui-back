<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    public function test_admin_can_view_dashboard(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
    }

    public function test_dashboard_contains_expected_props(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->has('today_orders_count')
            ->has('yesterday_orders_count')
            ->has('preparing_orders_count')
            ->has('monthly_orders_count')
            ->has('orders_limit')
            ->has('net_profit_month')
            ->has('orders_by_branch')
            ->has('recent_orders')
        );
    }

    public function test_dashboard_today_orders_count_is_correct(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $customer = Customer::factory()->create();

        Order::factory()->count(3)->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
        ]);

        // Order from another restaurant — must not be counted
        $other = Restaurant::factory()->create();
        $otherBranch = Branch::factory()->create(['restaurant_id' => $other->id]);
        $otherCustomer = Customer::factory()->create();
        Order::factory()->create([
            'restaurant_id' => $other->id,
            'branch_id' => $otherBranch->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->where('today_orders_count', 3)
        );
    }

    public function test_dashboard_recent_orders_max_ten(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $customer = Customer::factory()->create();

        Order::factory()->count(15)->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->has('recent_orders', 10)
        );
    }

    public function test_unauthenticated_user_redirected_from_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }
}
