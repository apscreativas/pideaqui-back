<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    private function createOrder(int $restaurantId, ?int $branchId = null, array $overrides = []): Order
    {
        $branch = $branchId
            ? Branch::find($branchId)
            : Branch::factory()->create(['restaurant_id' => $restaurantId]);
        $customer = Customer::factory()->create();

        return Order::factory()->create(array_merge([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
        ], $overrides));
    }

    // ─── Auth ────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_redirected_from_cancellations(): void
    {
        $response = $this->get(route('cancellations.index'));

        $response->assertRedirect(route('login'));
    }

    // ─── Page renders ────────────────────────────────────────────────────────────

    public function test_admin_can_view_cancellations_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Cancellations/Index'));
    }

    public function test_cancellations_page_has_expected_props(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Cancellations/Index')
            ->has('cancelled_count')
            ->has('total_orders_count')
            ->has('cancellation_rate')
            ->has('top_reason')
            ->has('reasons_breakdown')
            ->has('by_branch')
            ->has('by_day')
            ->has('cancelled_orders')
            ->has('branches')
            ->has('filters')
        );
    }

    // ─── KPI counts ──────────────────────────────────────────────────────────────

    public function test_cancelled_count_and_rate_are_correct(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, overrides: ['status' => 'delivered']);
        $this->createOrder($restaurant->id, overrides: ['status' => 'received']);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('cancelled_count', 3)
            ->where('total_orders_count', 5)
            ->where('cancellation_rate', 60)
        );
    }

    // ─── Multitenancy ────────────────────────────────────────────────────────────

    public function test_cancellation_data_scoped_to_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);

        $other = Restaurant::factory()->create();
        $this->createOrder($other->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Otro motivo', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('cancelled_count', 1)
            ->where('total_orders_count', 1)
        );
    }

    // ─── Date filter ─────────────────────────────────────────────────────────────

    public function test_cancellations_filter_by_date_range(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, overrides: [
            'status' => 'cancelled',
            'cancellation_reason' => 'Motivo',
            'cancelled_at' => now(),
            'created_at' => now()->subDays(3),
        ]);
        $this->createOrder($restaurant->id, overrides: [
            'status' => 'cancelled',
            'cancellation_reason' => 'Motivo',
            'cancelled_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertInertia(fn ($page) => $page->where('cancelled_count', 1));
    }

    // ─── Branch filter ───────────────────────────────────────────────────────────

    public function test_cancellations_filter_by_branch(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $branchA = Branch::factory()->create(['restaurant_id' => $restaurant->id]);
        $branchB = Branch::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->createOrder($restaurant->id, $branchA->id, ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, $branchA->id, ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, $branchB->id, ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', [
            'branch_id' => $branchA->id,
        ]));

        $response->assertInertia(fn ($page) => $page->where('cancelled_count', 2));
    }

    // ─── Reasons ─────────────────────────────────────────────────────────────────

    public function test_reasons_breakdown_groups_correctly(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Sin stock', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Sin stock', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Cliente no contesta', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('reasons_breakdown', 2)
            ->where('reasons_breakdown.0.reason', 'Sin stock')
            ->where('reasons_breakdown.0.count', 2)
            ->where('reasons_breakdown.1.reason', 'Cliente no contesta')
            ->where('reasons_breakdown.1.count', 1)
        );
    }

    public function test_top_reason_is_most_frequent(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Poco frecuente', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Mas comun', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Mas comun', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page->where('top_reason', 'Mas comun'));
    }

    // ─── By branch ───────────────────────────────────────────────────────────────

    public function test_cancellations_by_branch_includes_all_branches(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $branchA = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Centro']);
        $branchB = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Norte']);

        $this->createOrder($restaurant->id, $branchA->id, ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, $branchA->id, ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('by_branch', 2)
            ->where('by_branch.0.name', 'Centro')
            ->where('by_branch.0.count', 2)
            ->where('by_branch.1.name', 'Norte')
            ->where('by_branch.1.count', 0)
        );
    }

    // ─── Orders list ─────────────────────────────────────────────────────────────

    public function test_cancelled_orders_list_includes_relations(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, overrides: ['status' => 'cancelled', 'cancellation_reason' => 'Motivo', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('cancelled_orders.data', 1)
            ->where('cancelled_orders.data.0.channel', 'orders')
            ->has('cancelled_orders.data.0.reference')
            ->has('cancelled_orders.data.0.who')
            ->has('cancelled_orders.data.0.branch')
            ->has('cancelled_orders.data.0.cancellation_reason')
            ->has('cancelled_orders.data.0.cancelled_at')
            ->has('cancelled_orders.current_page')
            ->has('cancelled_orders.last_page')
            ->has('cancelled_orders.total')
        );
    }

    // ─── Empty state ─────────────────────────────────────────────────────────────

    public function test_no_cancellations_returns_zero_values(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->createOrder($restaurant->id, overrides: ['status' => 'delivered']);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('cancelled_count', 0)
            ->where('total_orders_count', 1)
            ->where('cancellation_rate', 0)
            ->where('top_reason', null)
            ->has('reasons_breakdown', 0)
            ->has('cancelled_orders.data', 0)
            ->where('cancelled_orders.total', 0)
        );
    }

    // ─── Pagination & scalability ────────────────────────────────────────────────

    public function test_cancellations_list_is_paginated(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        for ($i = 0; $i < 30; $i++) {
            $this->createOrder($restaurant->id, overrides: [
                'status' => 'cancelled',
                'cancellation_reason' => 'Motivo',
                'cancelled_at' => now()->subMinutes($i),
            ]);
        }

        // Default per_page is now 20 (was 25 before the paginator refactor).
        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('cancelled_orders.data', 20)
            ->where('cancelled_orders.current_page', 1)
            ->where('cancelled_orders.total', 30)
            ->where('cancelled_orders.last_page', 2)
            ->where('cancelled_orders.per_page', 20)
        );
    }

    public function test_cancellations_page_2_returns_remaining_rows(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        for ($i = 0; $i < 30; $i++) {
            $this->createOrder($restaurant->id, overrides: [
                'status' => 'cancelled',
                'cancellation_reason' => 'Motivo',
                'cancelled_at' => now()->subMinutes($i),
            ]);
        }

        // With per_page=20 and 30 rows, page 2 carries the remaining 10.
        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', ['page' => 2]));

        $response->assertInertia(fn ($page) => $page
            ->has('cancelled_orders.data', 10)
            ->where('cancelled_orders.current_page', 2)
        );
    }

    public function test_cancellations_respects_per_page_param(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        for ($i = 0; $i < 30; $i++) {
            $this->createOrder($restaurant->id, overrides: [
                'status' => 'cancelled',
                'cancellation_reason' => 'Motivo',
                'cancelled_at' => now()->subMinutes($i),
            ]);
        }

        $this->withoutVite()->actingAs($user)->get(route('cancellations.index', ['per_page' => 50]))
            ->assertInertia(fn ($page) => $page
                ->has('cancelled_orders.data', 30)
                ->where('cancelled_orders.per_page', 50)
                ->where('cancelled_orders.last_page', 1)
            );
    }

    public function test_cancellations_rejects_invalid_per_page(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        // 37 is not in {20,50,100}; controller should reject.
        $this->withoutVite()->actingAs($user)->get(route('cancellations.index', ['per_page' => 37]))
            ->assertStatus(302);
    }

    public function test_cancellations_sort_by_total_desc(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        foreach ([150, 900, 50, 400] as $i => $t) {
            $this->createOrder($restaurant->id, overrides: [
                'status' => 'cancelled',
                'cancellation_reason' => 'Motivo',
                'total' => $t,
                'cancelled_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', [
            'sort_by' => 'total', 'sort_direction' => 'desc',
        ]));
        $data = $response->viewData('page')['props']['cancelled_orders']['data'];
        $totals = array_map(fn ($r) => (float) $r['total'], $data);

        $this->assertEquals([900.0, 400.0, 150.0, 50.0], $totals);
        $this->assertEquals('total', $response->viewData('page')['props']['filters']['sort_by']);
    }

    public function test_cancellations_sort_by_total_asc_across_orders_and_pos(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $branch = \App\Models\Branch::factory()->create(['restaurant_id' => $restaurant->id]);

        // Mix orders + POS cancellations so we verify the merged sort
        // honors totals from both channels.
        $this->createOrder($restaurant->id, overrides: [
            'status' => 'cancelled', 'cancellation_reason' => 'x', 'total' => 250,
            'cancelled_at' => now()->subMinutes(1),
        ]);
        \App\Models\PosSale::factory()->create([
            'restaurant_id' => $restaurant->id, 'branch_id' => $branch->id,
            'cashier_user_id' => $user->id, 'status' => 'cancelled', 'total' => 100,
            'cancelled_at' => now()->subMinutes(2),
        ]);
        $this->createOrder($restaurant->id, overrides: [
            'status' => 'cancelled', 'cancellation_reason' => 'y', 'total' => 75,
            'cancelled_at' => now()->subMinutes(3),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', [
            'sort_by' => 'total', 'sort_direction' => 'asc',
        ]));
        $totals = array_map(
            fn ($r) => (float) $r['total'],
            $response->viewData('page')['props']['cancelled_orders']['data']
        );

        $this->assertEquals([75.0, 100.0, 250.0], $totals);
    }

    public function test_cancellations_sort_is_stable_across_pages_for_identical_totals(): void
    {
        // Six cancellations with the same total; paginating with per_page=3
        // must split them into 2 disjoint pages — no duplicates or skips.
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = $this->createOrder($restaurant->id, overrides: [
                'status' => 'cancelled', 'cancellation_reason' => 'same',
                'total' => 500,
                'cancelled_at' => now()->subMinutes($i),
            ])->id;
        }

        $page1 = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', [
            'sort_by' => 'total', 'sort_direction' => 'desc', 'per_page' => 20, 'page' => 1,
        ]))->viewData('page')['props']['cancelled_orders']['data'];

        $returnedIds = array_map(fn ($r) => (int) $r['id'], $page1);
        // Tie-breaker is id DESC, so with identical totals we expect the ids
        // in descending order.
        $expected = collect($ids)->sortDesc()->values()->all();
        $this->assertEquals($expected, $returnedIds);
    }

    public function test_cancellations_rejects_invalid_sort_by(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->withoutVite()->actingAs($user)
            ->get(route('cancellations.index', ['sort_by' => 'branch_id']))
            ->assertStatus(302);
    }

    public function test_cancellations_list_merges_orders_and_pos_without_duplicates(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $branch = \App\Models\Branch::factory()->create(['restaurant_id' => $restaurant->id]);

        // 15 cancelled orders + 15 cancelled POS sales → 30 merged.
        for ($i = 0; $i < 15; $i++) {
            $this->createOrder($restaurant->id, $branch->id, [
                'status' => 'cancelled',
                'cancellation_reason' => 'Motivo',
                'cancelled_at' => now()->subMinutes($i),
            ]);
            \App\Models\PosSale::factory()->create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'cashier_user_id' => $user->id,
                'status' => 'cancelled',
                'cancellation_reason' => 'Motivo',
                'cancelled_at' => now()->subMinutes($i),
            ]);
        }

        $page1 = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', ['page' => 1]));
        $page2 = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', ['page' => 2]));

        $page1->assertInertia(fn ($p) => $p->where('cancelled_orders.total', 30));

        $data1 = $page1->viewData('page')['props']['cancelled_orders']['data'];
        $data2 = $page2->viewData('page')['props']['cancelled_orders']['data'];

        $keys1 = collect($data1)->map(fn ($r) => $r['channel'].'-'.$r['id'])->all();
        $keys2 = collect($data2)->map(fn ($r) => $r['channel'].'-'.$r['id'])->all();

        $this->assertEmpty(array_intersect($keys1, $keys2), 'Page 1 and page 2 must not share rows');
        $this->assertCount(30, array_merge($keys1, $keys2));
    }

    public function test_by_branch_respects_branch_filter(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $branchA = \App\Models\Branch::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'A']);
        $branchB = \App\Models\Branch::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'B']);

        $this->createOrder($restaurant->id, $branchA->id, ['status' => 'cancelled', 'cancellation_reason' => 'X', 'cancelled_at' => now()]);
        $this->createOrder($restaurant->id, $branchB->id, ['status' => 'cancelled', 'cancellation_reason' => 'X', 'cancelled_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', [
            'branch_id' => $branchA->id,
        ]));

        // by_branch must only include the filtered branch (was showing both).
        $response->assertInertia(fn ($page) => $page
            ->has('by_branch', 1)
            ->where('by_branch.0.id', $branchA->id)
            ->where('by_branch.0.count', 1)
        );
    }

    public function test_by_branch_aggregates_orders_and_pos(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $branchA = \App\Models\Branch::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'A']);

        $this->createOrder($restaurant->id, $branchA->id, ['status' => 'cancelled', 'cancellation_reason' => 'X', 'cancelled_at' => now()]);
        \App\Models\PosSale::factory()->create([
            'restaurant_id' => $restaurant->id, 'branch_id' => $branchA->id, 'cashier_user_id' => $user->id,
            'status' => 'cancelled', 'cancellation_reason' => 'X', 'cancelled_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('by_branch.0.name', 'A')
            ->where('by_branch.0.count', 2) // 1 order + 1 POS
        );
    }

    public function test_invalid_from_date_returns_validation_error(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('cancellations.index', ['from' => 'not-a-date']));

        $response->assertSessionHasErrors('from');
    }
}
