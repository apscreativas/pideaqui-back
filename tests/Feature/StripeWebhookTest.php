<?php

namespace Tests\Feature;

use App\Models\BillingAudit;
use App\Models\BillingSetting;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cashier.webhook.secret' => null]);
    }

    private function fireWebhook(array $payload)
    {
        return $this->postJson(route('stripe.webhook'), $payload);
    }

    private function makeRestaurant(array $attrs = []): Restaurant
    {
        return Restaurant::factory()->subscription()->create(array_merge([
            'stripe_id' => 'cus_test_'.\Illuminate\Support\Str::random(10),
            'status' => 'active',
        ], $attrs));
    }

    // ─── Deduplication ───────────────────────────────────────────────────────

    public function test_duplicate_webhook_event_is_not_processed_twice(): void
    {
        $r = $this->makeRestaurant(['status' => 'active']);
        BillingSetting::set('payment_grace_period_days', '7');

        $payload = [
            'id' => 'evt_dedup_test_1',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'customer' => $r->stripe_id,
                'id' => 'in_test_1',
                'attempt_count' => 1,
            ]],
        ];

        $this->fireWebhook($payload)->assertOk();
        $this->assertEquals('grace_period', $r->fresh()->status);
        $firstAuditCount = BillingAudit::where('restaurant_id', $r->id)->where('action', 'payment_failed')->count();
        $this->assertEquals(1, $firstAuditCount);

        $this->fireWebhook($payload)->assertOk();
        $this->assertEquals(
            $firstAuditCount,
            BillingAudit::where('restaurant_id', $r->id)->where('action', 'payment_failed')->count(),
            'Duplicate webhook must not create a second audit row'
        );

        $this->assertEquals(1, DB::table('stripe_webhook_events')->where('stripe_event_id', 'evt_dedup_test_1')->count());
    }

    public function test_different_events_with_different_ids_are_both_processed(): void
    {
        $r = $this->makeRestaurant(['status' => 'active']);
        BillingSetting::set('payment_grace_period_days', '7');

        $this->fireWebhook([
            'id' => 'evt_a',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => $r->stripe_id, 'id' => 'in_1', 'attempt_count' => 1]],
        ])->assertOk();

        $r->update(['status' => 'active']);
        $this->fireWebhook([
            'id' => 'evt_b',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => $r->stripe_id, 'id' => 'in_2', 'attempt_count' => 2]],
        ])->assertOk();

        $this->assertEquals(2, DB::table('stripe_webhook_events')->count());
    }

    // ─── invoice.payment_failed ─────────────────────────────────────────────

    public function test_invoice_payment_failed_transitions_active_to_grace(): void
    {
        $r = $this->makeRestaurant(['status' => 'active']);
        BillingSetting::set('payment_grace_period_days', '5');

        $this->fireWebhook([
            'id' => 'evt_pay_fail_1',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => $r->stripe_id, 'id' => 'in_abc', 'attempt_count' => 1]],
        ])->assertOk();

        $fresh = $r->fresh();
        $this->assertEquals('grace_period', $fresh->status);
        $this->assertNotNull($fresh->grace_period_ends_at);

        $audit = BillingAudit::where('restaurant_id', $r->id)->where('action', 'payment_failed')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('in_abc', $audit->payload['invoice_id']);
    }

    public function test_invoice_payment_failed_does_not_clobber_grace_period(): void
    {
        $endsAt = now()->addDays(3)->startOfSecond();
        $r = $this->makeRestaurant([
            'status' => 'grace_period',
            'grace_period_ends_at' => $endsAt,
        ]);

        $this->fireWebhook([
            'id' => 'evt_pay_fail_2',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => $r->stripe_id, 'id' => 'in_xyz', 'attempt_count' => 2]],
        ])->assertOk();

        $fresh = $r->fresh();
        $this->assertEquals('grace_period', $fresh->status);
        $this->assertEquals($endsAt->toIso8601String(), $fresh->grace_period_ends_at->toIso8601String());
    }

    // ─── invoice.paid ───────────────────────────────────────────────────────

    public function test_invoice_paid_reactivates_grace_period_restaurant(): void
    {
        $r = $this->makeRestaurant([
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays(3),
        ]);

        $this->fireWebhook([
            'id' => 'evt_paid_1',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'customer' => $r->stripe_id,
                'id' => 'in_paid_1',
                'amount_paid' => 19900,
            ]],
        ])->assertOk();

        $fresh = $r->fresh();
        $this->assertEquals('active', $fresh->status);
        $this->assertNull($fresh->grace_period_ends_at);

        $audit = BillingAudit::where('restaurant_id', $r->id)->where('action', 'payment_succeeded')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('grace_period', $audit->payload['previous_status']);
    }

    public function test_invoice_paid_reactivates_suspended_restaurant(): void
    {
        $r = $this->makeRestaurant(['status' => 'suspended', 'is_active' => false]);

        $this->fireWebhook([
            'id' => 'evt_paid_2',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'customer' => $r->stripe_id,
                'id' => 'in_paid_2',
                'amount_paid' => 29900,
            ]],
        ])->assertOk();

        $fresh = $r->fresh();
        $this->assertEquals('active', $fresh->status);

        $audit = BillingAudit::where('restaurant_id', $r->id)->where('action', 'reactivated')->first();
        $this->assertNotNull($audit);
    }

    // ─── customer.subscription.deleted ──────────────────────────────────────

    public function test_subscription_deleted_suspends_restaurant(): void
    {
        $r = $this->makeRestaurant(['status' => 'active']);
        $r->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_del',
            'stripe_status' => 'active',
            'stripe_price' => 'price_x',
            'quantity' => 1,
        ]);

        $this->fireWebhook([
            'id' => 'evt_del_1',
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'id' => 'sub_test_del',
                'customer' => $r->stripe_id,
                'status' => 'canceled',
                'items' => ['data' => []],
            ]],
        ])->assertOk();

        $fresh = $r->fresh();
        $this->assertEquals('suspended', $fresh->status);
        $this->assertFalse((bool) $fresh->is_active);

        $audit = BillingAudit::where('restaurant_id', $r->id)->where('action', 'suspended')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('subscription_deleted', $audit->payload['reason']);
    }

    // ─── Unknown customer / malformed events ─────────────────────────────────

    public function test_webhook_for_unknown_customer_does_not_error(): void
    {
        $this->fireWebhook([
            'id' => 'evt_unknown_1',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => 'cus_does_not_exist', 'id' => 'in_orphan', 'attempt_count' => 1]],
        ])->assertOk();

        $this->assertEquals(0, BillingAudit::count());
    }

    public function test_event_without_id_is_not_deduped_but_still_handled(): void
    {
        $r = $this->makeRestaurant(['status' => 'active']);

        $this->fireWebhook([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => $r->stripe_id, 'id' => 'in_no_evt_id', 'attempt_count' => 1]],
        ])->assertOk();

        $this->assertEquals('grace_period', $r->fresh()->status);
        $this->assertEquals(0, DB::table('stripe_webhook_events')->count());
    }
}
