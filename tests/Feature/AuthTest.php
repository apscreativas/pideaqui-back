<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ─── Admin Restaurante ────────────────────────────────────────────────────

    public function test_login_page_is_accessible_to_guests(): void
    {
        $response = $this->withoutVite()->get('/login');

        $response->assertStatus(200);
    }

    public function test_authenticated_users_are_redirected_from_login(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->get('/login');

        $response->assertRedirect();
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);

        $response = $this->withoutVite()->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_cannot_login_with_wrong_password(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_logout(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_forgot_password_page_is_accessible(): void
    {
        $response = $this->withoutVite()->get('/forgot-password');

        $response->assertStatus(200);
    }

    // ─── SuperAdmin ───────────────────────────────────────────────────────────

    public function test_super_admin_login_page_is_accessible(): void
    {
        $response = $this->withoutVite()->get('/super/login');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_login_with_valid_credentials(): void
    {
        $superAdmin = SuperAdmin::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $response = $this->withoutVite()->post('/super/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('super.dashboard'));
        $this->assertAuthenticatedAs($superAdmin, 'superadmin');
    }

    public function test_super_admin_cannot_login_with_wrong_password(): void
    {
        $superAdmin = SuperAdmin::factory()->create();

        $response = $this->withoutVite()->post('/super/login', [
            'email' => $superAdmin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('superadmin');
    }

    public function test_admin_credentials_do_not_work_for_super_admin_login(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);

        $response = $this->withoutVite()->post('/super/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('superadmin');
    }
}
