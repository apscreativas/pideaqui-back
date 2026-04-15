<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\PaymentMethod;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\LimitService;
use App\Services\StatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    private function setupRestaurant(array $attrs = []): Restaurant
    {
        $restaurant = Restaurant::factory()->create(array_merge([
            'is_active' => true,
            'orders_limit' => 10,
        ], $attrs));

        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        PaymentMethod::factory()->terminal()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        return $restaurant;
    }

    private function adminFor(Restaurant $r): User
    {
        return User::factory()->create(['restaurant_id' => $r->id]);
    }

    private function branch(Restaurant $r): Branch
    {
        return Branch::factory()->create(['restaurant_id' => $r->id, 'is_active' => true]);
    }

    private function product(Restaurant $r, float $price = 100.00): Product
    {
        $cat = Category::factory()->create(['restaurant_id' => $r->id, 'is_active' => true]);

        return Product::factory()->create([
            'restaurant_id' => $r->id,
            'category_id' => $cat->id,
            'price' => $price,
            'production_cost' => 30.00,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Branch $branch, Product $product, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => $product->price, 'modifiers' => []],
            ],
        ], $overrides);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_pos(): void
    {
        $this->get(route('pos.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_pos_historial(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $this->branch($r);

        $this->withoutVite()->actingAs($admin)->get(route('pos.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($p) => $p
                ->component('Pos/Index')
                ->has('sales')
                ->has('branches')
                ->has('categories')
                ->has('paymentMethods')
                ->has('totals.tickets')
                ->has('totals.revenue')
                ->has('totals.open_count')
                ->has('totals.cancelled_count')
            );
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_create_valid_pos_sale_starts_in_preparing(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r, 100.00);

        $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product))
            ->assertRedirect()
            ->assertSessionHas('success');

        $sale = PosSale::latest()->first();
        $this->assertEquals('preparing', $sale->status);
        $this->assertEquals($r->id, $sale->restaurant_id);
        $this->assertEquals($branch->id, $sale->branch_id);
        $this->assertEquals($admin->id, $sale->cashier_user_id);
        $this->assertEquals('200.00', $sale->subtotal);
        $this->assertEquals('200.00', $sale->total);
        $this->assertStringStartsWith('POS-', $sale->ticket_number);
        $this->assertEquals(1, $sale->items()->count());
    }

    public function test_ticket_number_increments_per_restaurant(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);

        $this->actingAs($admin)->post(route('pos.sales.store'), $this->payload($branch, $product));
        $this->actingAs($admin)->post(route('pos.sales.store'), $this->payload($branch, $product));
        $this->actingAs($admin)->post(route('pos.sales.store'), $this->payload($branch, $product));

        $tickets = PosSale::orderBy('id')->pluck('ticket_number')->toArray();
        $this->assertEquals(['POS-0001', 'POS-0002', 'POS-0003'], $tickets);
    }

    // ─── Validation failures ──────────────────────────────────────────────────

    public function test_create_with_inactive_product_rejected(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);
        $product->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product))
            ->assertSessionHasErrors('items');

        $this->assertEquals(0, PosSale::count());
    }

    public function test_create_with_other_restaurant_product_rejected(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $other = $this->setupRestaurant();
        $otherProduct = $this->product($other);

        $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $otherProduct))
            ->assertSessionHasErrors('items');
    }

    public function test_anti_tampering_unit_price_rejected(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r, 100.00);

        $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product, [
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1.00, 'modifiers' => []]],
            ]))
            ->assertSessionHasErrors('items');
    }

    public function test_required_modifier_must_be_present(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $r->id,
            'product_id' => $product->id,
            'is_required' => true,
            'is_active' => true,
        ]);
        ModifierOption::factory()->create(['modifier_group_id' => $group->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product))
            ->assertSessionHasErrors('items');
    }

    public function test_inline_modifier_creates_correct_snapshot(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r, 100.00);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $r->id, 'product_id' => $product->id, 'is_active' => true, 'is_required' => false,
        ]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id, 'is_active' => true, 'price_adjustment' => 15.00, 'production_cost' => 5.00,
        ]);

        $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product, [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'modifiers' => [['modifier_option_id' => $option->id, 'price_adjustment' => 15.00]],
                ]],
            ]))
            ->assertSessionHas('success');

        $sale = PosSale::latest()->first();
        $this->assertEquals('115.00', $sale->total);
        $mod = $sale->items()->first()->modifiers()->first();
        $this->assertEquals($option->id, $mod->modifier_option_id);
        $this->assertNull($mod->modifier_option_template_id);
        $this->assertEquals('15.00', $mod->price_adjustment);
    }

    // ─── Branch authorization ─────────────────────────────────────────────────

    public function test_operator_can_create_at_assigned_branch(): void
    {
        $r = $this->setupRestaurant();
        $branch = $this->branch($r);
        $product = $this->product($r);
        $op = User::factory()->create(['restaurant_id' => $r->id, 'role' => 'operator']);
        $op->branches()->attach($branch->id);

        $this->actingAs($op)
            ->post(route('pos.sales.store'), $this->payload($branch, $product))
            ->assertSessionHas('success');
    }

    public function test_operator_cannot_create_at_unassigned_branch(): void
    {
        $r = $this->setupRestaurant();
        $assigned = $this->branch($r);
        $forbidden = $this->branch($r);
        $product = $this->product($r);
        $op = User::factory()->create(['restaurant_id' => $r->id, 'role' => 'operator']);
        $op->branches()->attach($assigned->id);

        $this->actingAs($op)
            ->post(route('pos.sales.store'), $this->payload($forbidden, $product))
            ->assertSessionHasErrors('branch_id');
    }

    // ─── State machine ────────────────────────────────────────────────────────

    public function test_pay_without_payments_rejected_even_from_preparing(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 100,
        ]);

        $this->actingAs($admin)
            ->put(route('pos.sales.pay', $sale))
            ->assertSessionHasErrors('payments');

        $this->assertEquals('preparing', $sale->fresh()->status);
    }

    public function test_pay_with_insufficient_payments_rejected(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 200,
        ]);
        PosPayment::factory()->terminal(50.00)->create([
            'pos_sale_id' => $sale->id, 'registered_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('pos.sales.pay', $sale))
            ->assertSessionHasErrors('payments');
    }

    public function test_pay_with_full_payments_completes_sale(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 200,
        ]);
        PosPayment::factory()->terminal(200.00)->create([
            'pos_sale_id' => $sale->id, 'registered_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('pos.sales.pay', $sale))
            ->assertSessionHas('success');

        $fresh = $sale->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_mixed_payment_cash_and_terminal_auto_closes_sale(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 300,
        ]);

        // Split 1: terminal $100 — still $200 pending, status stays preparing
        $this->actingAs($admin)
            ->post(route('pos.sales.payments.store', $sale), [
                'payment_method_type' => 'terminal', 'amount' => 100.00,
            ])->assertSessionHas('success');

        $this->assertEquals('preparing', $sale->fresh()->status);

        // Split 2: cash $200 (client hands over $250, gets $50 change) — total now covered
        // → backend auto-closes to 'paid' in the SAME request. No manual close needed.
        $this->actingAs($admin)
            ->post(route('pos.sales.payments.store', $sale), [
                'payment_method_type' => 'cash', 'amount' => 200.00, 'cash_received' => 250.00,
            ])->assertSessionHas('success');

        $fresh = $sale->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);

        $payments = $fresh->payments;
        $this->assertCount(2, $payments);
        $cashPayment = $payments->firstWhere('payment_method_type', 'cash');
        $this->assertEquals('50.00', $cashPayment->change_given);
    }

    public function test_cash_received_greater_than_pending_is_accepted(): void
    {
        // Reproduces the bug: total $290, customer gives $2000. amount applied must be 290,
        // change 1710, sale auto-closes to paid. Must NOT reject because received > pending.
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 290,
        ]);

        $this->actingAs($admin)
            ->post(route('pos.sales.payments.store', $sale), [
                'payment_method_type' => 'cash',
                'amount' => 290.00,        // monto aplicado = pending
                'cash_received' => 2000.00, // lo que entrega el cliente
            ])
            ->assertSessionHas('success');

        $fresh = $sale->fresh();
        $this->assertEquals('paid', $fresh->status);
        $payment = $fresh->payments->first();
        $this->assertEquals('290.00', $payment->amount);
        $this->assertEquals('2000.00', $payment->cash_received);
        $this->assertEquals('1710.00', $payment->change_given);
    }

    public function test_single_payment_covering_total_auto_closes_sale(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 150,
        ]);

        $this->actingAs($admin)
            ->post(route('pos.sales.payments.store', $sale), [
                'payment_method_type' => 'terminal', 'amount' => 150.00,
            ])->assertSessionHas('success');

        $fresh = $sale->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_partial_payment_does_not_close_sale(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 500,
        ]);

        $this->actingAs($admin)
            ->post(route('pos.sales.payments.store', $sale), [
                'payment_method_type' => 'terminal', 'amount' => 200.00,
            ])->assertSessionHas('success');

        $fresh = $sale->fresh();
        $this->assertEquals('preparing', $fresh->status);
        $this->assertNull($fresh->paid_at);
        $this->assertEquals(300.0, $fresh->pendingAmount());
    }

    public function test_payment_amount_exceeding_pending_rejected(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 100,
        ]);

        $this->actingAs($admin)
            ->post(route('pos.sales.payments.store', $sale), [
                'payment_method_type' => 'terminal', 'amount' => 150.00,
            ])->assertSessionHasErrors('amount');
    }

    public function test_remove_payment_on_open_sale_allowed(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 100,
        ]);
        $payment = PosPayment::factory()->terminal(50.00)->create([
            'pos_sale_id' => $sale->id, 'registered_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('pos.sales.payments.destroy', [$sale, $payment]))
            ->assertSessionHas('success');

        $this->assertNull(PosPayment::find($payment->id));
    }

    public function test_remove_payment_on_paid_rejected(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->paid()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 100,
        ]);
        $payment = PosPayment::factory()->terminal(100.00)->create([
            'pos_sale_id' => $sale->id, 'registered_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('pos.sales.payments.destroy', [$sale, $payment]))
            ->assertSessionHasErrors('status');

        $this->assertNotNull(PosPayment::find($payment->id));
    }

    public function test_cancel_preparing_allowed(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('pos.sales.cancel', $sale), ['cancellation_reason' => 'Cliente desistió'])
            ->assertSessionHas('success');

        $this->assertEquals('cancelled', $sale->fresh()->status);
        $this->assertNotNull($sale->fresh()->cancelled_at);
    }

    public function test_cancel_paid_rejected(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->paid()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('pos.sales.cancel', $sale), ['cancellation_reason' => 'X'])
            ->assertStatus(403);
    }

    // ─── No consume plan limit ────────────────────────────────────────────────

    public function test_pos_sales_do_not_consume_plan_limit(): void
    {
        $r = $this->setupRestaurant(['orders_limit' => 2]);
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);

        $beforeSummary = app(LimitService::class)->summary($r);

        $this->actingAs($admin)->post(route('pos.sales.store'), $this->payload($branch, $product));
        $this->actingAs($admin)->post(route('pos.sales.store'), $this->payload($branch, $product));
        $this->actingAs($admin)->post(route('pos.sales.store'), $this->payload($branch, $product));

        $afterSummary = app(LimitService::class)->summary($r->fresh());

        $this->assertEquals($beforeSummary['used'], $afterSummary['used']);
        $this->assertNull($afterSummary['reason']);
        $this->assertEquals(3, PosSale::count());
    }

    // ─── Statistics ──────────────────────────────────────────────────────────

    public function test_statistics_revenue_includes_pos_with_breakdown(): void
    {
        $r = $this->setupRestaurant(['orders_limit' => 100]);
        $branch = $this->branch($r);
        $admin = $this->adminFor($r);

        // 1 paid POS sale, $200 total
        PosSale::factory()->paid()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
            'total' => 200, 'subtotal' => 200,
        ]);

        $stats = app(StatisticsService::class)->getDashboardData($r, now()->startOfDay(), now()->endOfDay());

        // POS counter exposed
        $this->assertEquals(1, $stats['pos_sales_count']);

        // Total revenue now INCLUDES POS (was: orders only)
        $this->assertEquals(200.0, (float) $stats['revenue']);

        // Breakdown shows source separation
        $this->assertEquals(0.0, (float) $stats['revenue_breakdown']['orders']);
        $this->assertEquals(200.0, (float) $stats['revenue_breakdown']['pos']);

        // POS sales still NOT counted in plan limit
        $this->assertEquals(0, $stats['monthly_orders_count']);
    }

    // ─── Tenant isolation ────────────────────────────────────────────────────

    // ─── Financial data exposure (role-based) ────────────────────────────────

    public function test_admin_sees_production_cost_in_pos_sale_detail(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
        ]);
        $sale->items()->create([
            'product_id' => $this->product($r)->id,
            'product_name' => 'X',
            'quantity' => 1,
            'unit_price' => 100,
            'production_cost' => 30,
        ]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.sales.show', $sale));

        $response->assertInertia(fn ($p) => $p
            ->where('can_view_financials', true)
            ->where('sale.items.0.production_cost', '30.00')
        );
    }

    public function test_operator_does_not_see_production_cost_in_pos_sale_detail(): void
    {
        $r = $this->setupRestaurant();
        $branch = $this->branch($r);
        $product = $this->product($r);
        $op = User::factory()->create(['restaurant_id' => $r->id, 'role' => 'operator']);
        $op->branches()->attach($branch->id);

        $admin = $this->adminFor($r);  // creator
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => 'X',
            'quantity' => 1,
            'unit_price' => 100,
            'production_cost' => 30,
        ]);

        $response = $this->withoutVite()->actingAs($op)->get(route('pos.sales.show', $sale));

        $response->assertInertia(fn ($p) => $p
            ->where('can_view_financials', false)
            ->missing('sale.items.0.production_cost')
        );
    }

    public function test_operator_catalog_in_historial_has_no_production_cost(): void
    {
        $r = $this->setupRestaurant();
        $branch = $this->branch($r);
        $this->product($r);
        $op = User::factory()->create(['restaurant_id' => $r->id, 'role' => 'operator']);
        $op->branches()->attach($branch->id);

        $response = $this->withoutVite()->actingAs($op)->get(route('pos.index'));

        $response->assertInertia(fn ($p) => $p
            ->where('can_view_financials', false)
            ->missing('categories.0.products.0.production_cost')
        );
    }

    public function test_cannot_view_pos_sale_from_another_restaurant(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $other = $this->setupRestaurant();
        $sale = PosSale::factory()->create([
            'restaurant_id' => $other->id,
            'branch_id' => $this->branch($other)->id,
            'cashier_user_id' => $this->adminFor($other)->id,
        ]);

        $this->actingAs($admin)
            ->get(route('pos.sales.show', $sale))
            ->assertStatus(404);
    }

    // ─── Pagination, KPIs & scalability ──────────────────────────────────────

    public function test_pos_historial_returns_length_aware_paginator(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        // All within today so the default date range covers them.
        for ($i = 0; $i < 60; $i++) {
            PosSale::factory()->create([
                'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        // Default per_page is 20 so we expect 3 pages over 60 rows.
        $this->withoutVite()->actingAs($admin)->get(route('pos.index'))
            ->assertInertia(fn ($p) => $p
                ->has('sales.data', 20)
                ->where('sales.current_page', 1)
                ->where('sales.last_page', 3)
                ->where('sales.per_page', 20)
                ->where('sales.total', 60)
                ->where('sales.from', 1)
                ->where('sales.to', 20)
            );
    }

    public function test_pos_historial_respects_per_page_param(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        for ($i = 0; $i < 30; $i++) {
            PosSale::factory()->create([
                'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $this->withoutVite()->actingAs($admin)->get(route('pos.index', ['per_page' => 50]))
            ->assertInertia(fn ($p) => $p
                ->has('sales.data', 30)
                ->where('sales.per_page', 50)
                ->where('sales.total', 30)
                ->where('sales.last_page', 1)
            );
    }

    public function test_pos_historial_rejects_invalid_per_page(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);

        // 37 is not in the allowed set {20,50,100} — should 422.
        $this->withoutVite()->actingAs($admin)->get(route('pos.index', ['per_page' => 37]))
            ->assertStatus(302); // redirect back with validation errors
    }

    public function test_pos_historial_page_2_returns_next_slice(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        // 25 sales, page 2 with per_page=20 should contain the last 5.
        $created = [];
        for ($i = 0; $i < 25; $i++) {
            $created[] = PosSale::factory()->create([
                'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $this->withoutVite()->actingAs($admin)->get(route('pos.index', ['page' => 2]))
            ->assertInertia(fn ($p) => $p
                ->has('sales.data', 5)
                ->where('sales.current_page', 2)
                ->where('sales.from', 21)
                ->where('sales.to', 25)
            );
    }

    public function test_pos_historial_sort_by_total_desc_orders_correctly(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        // Totals in intentionally non-chronological order so the result can
        // only be right if the backend honors sort_by=total, not just fallback.
        $totals = [125.50, 900.00, 50.25, 300.00, 75.00];
        foreach ($totals as $i => $t) {
            PosSale::factory()->create([
                'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
                'total' => $t,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index', [
            'sort_by' => 'total',
            'sort_direction' => 'desc',
        ]));
        $data = $response->viewData('page')['props']['sales']['data'];
        $returned = array_map(fn ($r) => (float) $r['total'], $data);

        $this->assertEquals([900.00, 300.00, 125.50, 75.00, 50.25], $returned);
        // Filters echoed back so the frontend keeps active column highlighted.
        $this->assertEquals('total', $response->viewData('page')['props']['filters']['sort_by']);
        $this->assertEquals('desc', $response->viewData('page')['props']['filters']['sort_direction']);
    }

    public function test_pos_historial_sort_by_total_asc_orders_correctly(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        foreach ([300, 100, 200] as $i => $t) {
            PosSale::factory()->create([
                'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
                'total' => $t,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index', [
            'sort_by' => 'total', 'sort_direction' => 'asc',
        ]));
        $data = $response->viewData('page')['props']['sales']['data'];

        $this->assertEquals([100, 200, 300], array_map(fn ($r) => (int) $r['total'], $data));
    }

    public function test_pos_historial_sort_by_total_is_stable_on_ties_and_does_not_duplicate_across_pages(): void
    {
        // Five sales share the same total — the id DESC tie-breaker must
        // produce the same order on every request so paginating page 1 → 2
        // never shows the same sale twice or skips one.
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = PosSale::factory()->create([
                'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
                'total' => 200.00,
                'created_at' => now()->subMinutes($i),
            ])->id;
        }

        // Page 1 with per_page=2
        $page1 = $this->withoutVite()->actingAs($admin)->get(route('pos.index', [
            'sort_by' => 'total', 'sort_direction' => 'desc', 'per_page' => 20, 'page' => 1,
        ]))->viewData('page')['props']['sales']['data'];

        // Full sorted expectation: id DESC among ties.
        $expected = collect($ids)->sortDesc()->values()->all();
        $returned = array_map(fn ($r) => (int) $r['id'], $page1);
        $this->assertEquals($expected, $returned);
    }

    public function test_pos_historial_rejects_invalid_sort_by(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);

        // `payment_method` is not in the sort whitelist (it's a filter).
        $this->withoutVite()->actingAs($admin)
            ->get(route('pos.index', ['sort_by' => 'payment_method']))
            ->assertStatus(302);
    }

    public function test_pos_historial_rejects_invalid_sort_direction(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);

        $this->withoutVite()->actingAs($admin)
            ->get(route('pos.index', ['sort_by' => 'total', 'sort_direction' => 'up']))
            ->assertStatus(302);
    }

    public function test_pos_historial_order_is_stable_for_identical_timestamps(): void
    {
        // Three sales created at the same exact moment: the tie-breaker must
        // order them by id DESC deterministically.
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        $now = now()->startOfMinute();
        $sales = [];
        for ($i = 0; $i < 3; $i++) {
            $sales[] = PosSale::factory()->create([
                'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
                'created_at' => $now,
            ]);
        }

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index'));
        $data = $response->viewData('page')['props']['sales']['data'];

        $ids = array_column($data, 'id');
        $expected = collect($sales)->pluck('id')->sortDesc()->values()->all();
        $this->assertEquals($expected, $ids);
    }

    public function test_pos_kpis_cover_full_range_regardless_of_status_filter(): void
    {
        // The four KPI cards are a breakdown BY status. A single-status filter
        // should narrow the list but keep KPIs showing the full picture.
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        PosSale::factory()->preparing()->create(['restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 100]);
        PosSale::factory()->paid()->create(['restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 200]);
        PosSale::factory()->paid()->create(['restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 300]);
        PosSale::factory()->create(['restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'status' => 'cancelled', 'total' => 50]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index', ['status' => 'paid']));

        $response->assertInertia(fn ($p) => $p
            ->where('totals.tickets', 4)                                // all in range
            ->where('totals.revenue', fn ($v) => (float) $v === 500.0)  // only paid
            ->where('totals.open_count', 1)                             // preparing
            ->where('totals.cancelled_count', 1)
            ->has('sales.data', 2)                                      // list narrowed
        );
    }

    public function test_pos_kpis_respect_date_range(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        PosSale::factory()->paid()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
            'total' => 100, 'created_at' => now()->subDays(3),
        ]);
        PosSale::factory()->paid()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
            'total' => 200, 'created_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertInertia(fn ($p) => $p
            ->where('totals.tickets', 1)
            ->where('totals.revenue', fn ($v) => (float) $v === 200.0)
        );
    }

    public function test_pos_payment_method_filter_uses_where_exists(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);

        $saleA = PosSale::factory()->paid()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 100,
        ]);
        PosPayment::factory()->terminal(100)->create(['pos_sale_id' => $saleA->id, 'registered_by_user_id' => $admin->id]);

        $saleB = PosSale::factory()->paid()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 100,
        ]);
        PosPayment::factory()->create(['pos_sale_id' => $saleB->id, 'registered_by_user_id' => $admin->id, 'payment_method_type' => 'cash', 'amount' => 100]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index', ['payment_method' => 'terminal']));

        $response->assertInertia(fn ($p) => $p
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $saleA->id)
        );
    }

    public function test_pos_historial_excludes_other_tenant_sales_with_same_timestamp(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $other = $this->setupRestaurant();
        $otherBranch = $this->branch($other);
        $otherAdmin = $this->adminFor($other);

        $now = now();
        PosSale::factory()->create(['restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'created_at' => $now]);
        PosSale::factory()->create(['restaurant_id' => $other->id, 'branch_id' => $otherBranch->id, 'cashier_user_id' => $otherAdmin->id, 'created_at' => $now]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index'));

        $response->assertInertia(fn ($p) => $p
            ->has('sales.data', 1)
            ->where('totals.tickets', 1)
        );
    }

    public function test_pos_list_does_not_expose_production_cost_in_items(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);

        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => 'X',
            'quantity' => 1,
            'unit_price' => 100,
            'production_cost' => 42,
        ]);

        $response = $this->withoutVite()->actingAs($admin)->get(route('pos.index'));

        $response->assertInertia(fn ($p) => $p
            ->missing('sales.data.0.items.0.production_cost')
        );
    }

    // ─── Operational gate (billing status / period) ───────────────────────────

    public function test_pos_create_blocked_when_restaurant_suspended(): void
    {
        $r = $this->setupRestaurant();
        $r->update(['status' => 'suspended', 'is_active' => false]);
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);

        $response = $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product));

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($msg) => str_contains($msg, 'suspendido'));
        $this->assertEquals(0, PosSale::count());
    }

    public function test_pos_create_blocked_when_period_expired(): void
    {
        $r = $this->setupRestaurant([
            'orders_limit_start' => now()->subDays(30),
            'orders_limit_end' => now()->subDays(1),
        ]);
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);

        $response = $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product));

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($msg) => str_contains(strtolower($msg), 'expir'));
        $this->assertEquals(0, PosSale::count());
    }

    public function test_pos_create_allowed_when_order_limit_reached(): void
    {
        // POS does NOT consume the order quota. Hitting orders_limit must not
        // block POS — cashiers keep selling in store.
        $r = $this->setupRestaurant(['orders_limit' => 2]);
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $product = $this->product($r);

        $customer = \App\Models\Customer::factory()->create();
        for ($i = 0; $i < 2; $i++) {
            \App\Models\Order::factory()->create([
                'restaurant_id' => $r->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('pos.sales.store'), $this->payload($branch, $product))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(1, PosSale::count());
    }

    public function test_pos_payment_allowed_even_when_suspended(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id, 'total' => 150,
        ]);

        $r->update(['status' => 'suspended', 'is_active' => false]);

        $this->actingAs($admin)
            ->post(route('pos.sales.payments.store', $sale), [
                'payment_method_type' => 'terminal', 'amount' => 150.00,
            ])
            ->assertSessionHas('success');

        $this->assertEquals('paid', $sale->fresh()->status);
    }

    public function test_pos_cancel_allowed_even_when_suspended(): void
    {
        $r = $this->setupRestaurant();
        $admin = $this->adminFor($r);
        $branch = $this->branch($r);
        $sale = PosSale::factory()->preparing()->create([
            'restaurant_id' => $r->id, 'branch_id' => $branch->id, 'cashier_user_id' => $admin->id,
        ]);

        $r->update(['status' => 'suspended', 'is_active' => false]);

        $this->actingAs($admin)
            ->put(route('pos.sales.cancel', $sale), ['cancellation_reason' => 'Restaurant suspended'])
            ->assertSessionHas('success');

        $this->assertEquals('cancelled', $sale->fresh()->status);
    }

    public function test_pos_index_exposes_can_operate_when_blocked(): void
    {
        $r = $this->setupRestaurant();
        $r->update(['status' => 'suspended', 'is_active' => false]);
        $admin = $this->adminFor($r);
        $this->branch($r);

        $this->withoutVite()->actingAs($admin)->get(route('pos.index'))
            ->assertInertia(fn ($p) => $p
                ->where('billing.can_operate', false)
                ->where('billing.block_reason', 'suspended')
                ->where('billing.block_message', fn ($m) => str_contains((string) $m, 'suspendido'))
            );
    }
}
