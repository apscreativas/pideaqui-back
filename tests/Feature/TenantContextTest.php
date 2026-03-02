<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_dashboard(): void
    {
        $response = $this->withoutVite()->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_admin_without_restaurant_cannot_access_dashboard(): void
    {
        $user = User::factory()->create(['restaurant_id' => null]);

        $response = $this->withoutVite()->actingAs($user)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_admin_with_restaurant_can_access_dashboard(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_admin_cannot_access_super_admin_dashboard(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->get('/super/dashboard');

        $response->assertRedirect(route('super.login'));
    }
}
