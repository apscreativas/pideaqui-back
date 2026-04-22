<?php

namespace Tests\Unit;

use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Onboarding\Dto\ProvisionRestaurantData;
use App\Services\Onboarding\RestaurantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestaurantProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private RestaurantProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RestaurantProvisioningService::class);

        if (! Plan::gracePlan()) {
            Plan::factory()->grace()->create();
        }
    }

    private function graceDto(array $overrides = []): ProvisionRestaurantData
    {
        return new ProvisionRestaurantData(
            source: $overrides['source'] ?? 'super_admin',
            restaurantName: $overrides['restaurantName'] ?? 'Test Restaurant',
            adminName: $overrides['adminName'] ?? 'Admin Test',
            adminEmail: $overrides['adminEmail'] ?? 'admin@test.com',
            adminPassword: $overrides['adminPassword'] ?? 'password123',
            billingMode: $overrides['billingMode'] ?? 'grace',
            actorId: $overrides['actorId'] ?? 1,
            ipAddress: $overrides['ipAddress'] ?? '127.0.0.1',
        );
    }

    public function test_provisions_grace_restaurant_with_correct_defaults(): void
    {
        $restaurant = $this->service->provision($this->graceDto());

        $this->assertEquals('subscription', $restaurant->billing_mode);
        $this->assertEquals('grace_period', $restaurant->status);
        $this->assertNotNull($restaurant->grace_period_ends_at);
        $this->assertEquals(50, $restaurant->orders_limit);
        $this->assertEquals(1, $restaurant->max_branches);
        $this->assertTrue($restaurant->is_active);
        $this->assertFalse($restaurant->allows_delivery);
        $this->assertTrue($restaurant->allows_pickup);
        $this->assertFalse($restaurant->allows_dine_in);
    }

    public function test_creates_admin_user_with_role_and_restaurant_id(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['adminEmail' => 'new@test.com']));

        $user = User::where('email', 'new@test.com')->firstOrFail();

        $this->assertEquals('admin', $user->role);
        $this->assertEquals($restaurant->id, $user->restaurant_id);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_seeds_three_payment_methods(): void
    {
        $restaurant = $this->service->provision($this->graceDto());

        $this->assertEquals(3, PaymentMethod::where('restaurant_id', $restaurant->id)->count());
        $this->assertDatabaseHas('payment_methods', [
            'restaurant_id' => $restaurant->id, 'type' => 'cash', 'is_active' => true,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'restaurant_id' => $restaurant->id, 'type' => 'terminal', 'is_active' => false,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'restaurant_id' => $restaurant->id, 'type' => 'transfer', 'is_active' => false,
        ]);
    }

    public function test_logs_billing_audit_with_super_admin_actor_type(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['source' => 'super_admin']));

        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'restaurant_created',
            'actor_type' => 'super_admin',
        ]);
    }

    public function test_logs_self_signup_actor_type_when_source_self_signup(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['source' => 'self_signup']));

        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'restaurant_created',
            'actor_type' => 'self_signup',
        ]);
    }

    public function test_provisions_manual_restaurant_with_provided_limits(): void
    {
        $dto = new ProvisionRestaurantData(
            source: 'super_admin',
            restaurantName: 'Manual Test',
            adminName: 'Admin',
            adminEmail: 'manual@test.com',
            adminPassword: 'password123',
            billingMode: 'manual',
            ordersLimit: 999,
            maxBranches: 7,
            ordersLimitStart: now()->startOfMonth(),
            ordersLimitEnd: now()->endOfMonth(),
            actorId: 1,
            ipAddress: '127.0.0.1',
        );

        $restaurant = $this->service->provision($dto);

        $this->assertEquals('manual', $restaurant->billing_mode);
        $this->assertEquals('active', $restaurant->status);
        $this->assertNull($restaurant->plan_id);
        $this->assertEquals(999, $restaurant->orders_limit);
        $this->assertEquals(7, $restaurant->max_branches);
    }

    public function test_generates_unique_slug_on_name_collision(): void
    {
        Restaurant::factory()->create(['name' => 'Colliding', 'slug' => 'colliding']);

        $restaurant = $this->service->provision($this->graceDto([
            'restaurantName' => 'Colliding',
            'adminEmail' => 'another@test.com',
        ]));

        $this->assertNotEquals('colliding', $restaurant->slug);
        $this->assertStringStartsWith('colliding-', $restaurant->slug);
    }

    public function test_sets_signup_source_super_admin(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['source' => 'super_admin']));
        $this->assertEquals('super_admin', $restaurant->signup_source);
    }

    public function test_sets_signup_source_self_signup(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['source' => 'self_signup']));
        $this->assertEquals('self_signup', $restaurant->signup_source);
    }

    public function test_super_admin_source_pre_verifies_email(): void
    {
        $restaurant = $this->service->provision($this->graceDto([
            'source' => 'super_admin',
            'adminEmail' => 'preverified@test.com',
        ]));

        $user = User::where('email', 'preverified@test.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_self_signup_source_does_not_pre_verify(): void
    {
        $restaurant = $this->service->provision($this->graceDto([
            'source' => 'self_signup',
            'adminEmail' => 'unverified@test.com',
        ]));

        $user = User::where('email', 'unverified@test.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_rolls_back_when_audit_fails(): void
    {
        Schema::drop('billing_audits');

        try {
            $this->service->provision($this->graceDto(['adminEmail' => 'rollback@test.com']));
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback@test.com']);
        $this->assertDatabaseMissing('restaurants', ['name' => 'Test Restaurant']);
    }
}
