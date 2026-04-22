<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\SuperAdmin;
use App\Services\Onboarding\Dto\ProvisionRestaurantData;
use App\Services\Onboarding\RestaurantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises slug handling through both real entry points (self-signup form
 * and SuperAdmin manual create) and through the provisioning service
 * directly to cover race-condition resilience.
 */
class SlugProvisioningTest extends TestCase
{
    use RefreshDatabase;

    // ─── Self-signup ─────────────────────────────────────────────────────────

    public function test_self_signup_uses_user_provided_slug(): void
    {
        $this->post(route('register.store'), [
            'restaurant_name' => 'Tacos El Rey',
            'slug' => 'mi-slug-bonito',
            'admin_name' => 'Ana',
            'email' => 'ana@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $this->assertDatabaseHas('restaurants', ['slug' => 'mi-slug-bonito']);
    }

    public function test_self_signup_rejects_reserved_slug(): void
    {
        $response = $this->post(route('register.store'), [
            'restaurant_name' => 'Tacos El Rey',
            'slug' => 'admin',
            'admin_name' => 'Ana',
            'email' => 'ana@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('restaurants', ['slug' => 'admin']);
    }

    public function test_self_signup_rejects_taken_slug(): void
    {
        Restaurant::factory()->create(['slug' => 'already-taken']);

        $response = $this->post(route('register.store'), [
            'restaurant_name' => 'Tacos El Rey',
            'slug' => 'already-taken',
            'admin_name' => 'Ana',
            'email' => 'ana@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_self_signup_auto_generates_slug_when_blank(): void
    {
        $this->post(route('register.store'), [
            'restaurant_name' => 'Tacos El Rey',
            'admin_name' => 'Ana',
            'email' => 'ana@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $this->assertDatabaseHas('restaurants', ['slug' => 'tacos-el-rey']);
    }

    // ─── SuperAdmin rename ───────────────────────────────────────────────────

    public function test_super_admin_can_rename_slug(): void
    {
        $sa = SuperAdmin::factory()->create();
        $restaurant = Restaurant::factory()->create(['slug' => 'old-name']);

        $response = $this->actingAs($sa, 'superadmin')
            ->patch(route('super.restaurants.rename-slug', $restaurant->id), [
                'slug' => 'new-name',
                'confirm' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'slug' => 'new-name']);
    }

    public function test_rename_requires_confirm_flag(): void
    {
        $sa = SuperAdmin::factory()->create();
        $restaurant = Restaurant::factory()->create(['slug' => 'old']);

        $response = $this->actingAs($sa, 'superadmin')
            ->patch(route('super.restaurants.rename-slug', $restaurant->id), [
                'slug' => 'new',
                'confirm' => false,
            ]);

        $response->assertSessionHasErrors('confirm');
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'slug' => 'old']);
    }

    public function test_rename_rejects_taken_slug(): void
    {
        $sa = SuperAdmin::factory()->create();
        Restaurant::factory()->create(['slug' => 'ocupado']);
        $restaurant = Restaurant::factory()->create(['slug' => 'mine']);

        $response = $this->actingAs($sa, 'superadmin')
            ->patch(route('super.restaurants.rename-slug', $restaurant->id), [
                'slug' => 'ocupado',
                'confirm' => true,
            ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_rename_allows_keeping_same_slug(): void
    {
        $sa = SuperAdmin::factory()->create();
        $restaurant = Restaurant::factory()->create(['slug' => 'same']);

        $response = $this->actingAs($sa, 'superadmin')
            ->patch(route('super.restaurants.rename-slug', $restaurant->id), [
                'slug' => 'same',
                'confirm' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'slug' => 'same']);
    }

    public function test_admin_route_for_slug_rename_does_not_exist(): void
    {
        // Admin users have no endpoint for slug rename; the feature is
        // limited to SuperAdmin. Document that assumption with a test.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('settings.general.rename-slug'));
    }

    // ─── Provisioning service directly ───────────────────────────────────────

    public function test_provisioning_retries_after_simulated_collision(): void
    {
        Restaurant::factory()->create(['slug' => 'collision']);

        $dto = new ProvisionRestaurantData(
            source: 'self_signup',
            restaurantName: 'Collision',
            adminName: 'Ana',
            adminEmail: 'collision@test.com',
            adminPassword: 'Password123',
            billingMode: 'manual',
            ordersLimit: 50,
            maxBranches: 1,
            slug: 'collision',
        );

        $restaurant = app(RestaurantProvisioningService::class)->provision($dto);

        // The provided slug was taken, so the service must fall back to
        // auto-generation and pick the next available suffix.
        $this->assertSame('collision-2', $restaurant->slug);
    }
}
