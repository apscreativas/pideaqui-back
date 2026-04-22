<?php

namespace Tests\Feature\Auth;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedUser(): User
    {
        $restaurant = Restaurant::factory()->grace()->selfSignup()->create();
        $user = User::factory()->unverified()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
        ]);

        return $user;
    }

    public function test_unverified_user_sees_verify_notice_page(): void
    {
        $user = $this->unverifiedUser();

        $response = $this->withoutVite()->actingAs($user)->get(route('verification.notice'));

        $response->assertStatus(200);
    }

    public function test_signed_link_for_already_verified_user_redirects_without_error(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now()->subDay(),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(route('dashboard').'?verified=1');
    }

    public function test_signed_link_verifies_email(): void
    {
        Event::fake();
        $user = $this->unverifiedUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(route('dashboard').'?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_invalid_hash_rejects_verification(): void
    {
        $user = $this->unverifiedUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'wrong-hash'],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_notification_is_throttled(): void
    {
        $user = $this->unverifiedUser();

        $response = null;
        for ($i = 0; $i < 7; $i++) {
            $response = $this->actingAs($user)->post(route('verification.send'));
        }

        $response->assertStatus(429);
    }

    public function test_self_signup_user_cannot_access_dashboard_until_verified(): void
    {
        $user = $this->unverifiedUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_super_admin_created_user_accesses_dashboard_directly(): void
    {
        $restaurant = Restaurant::factory()->create(['signup_source' => 'super_admin']);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_backfilled_old_users_are_not_blocked(): void
    {
        // Simulates an admin who existed before the verification requirement.
        $restaurant = Restaurant::factory()->create(['signup_source' => 'super_admin']);
        $oldUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now()->subYear(),
        ]);

        $response = $this->withoutVite()->actingAs($oldUser)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_unverified_user_login_redirects_to_verify_notice(): void
    {
        $user = $this->unverifiedUser();
        $user->update(['password' => \Illuminate\Support\Facades\Hash::make('password123')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_grace_days_remaining_exposed_in_inertia_props(): void
    {
        $restaurant = Restaurant::factory()->grace()->create([
            'grace_period_ends_at' => now()->addDays(7),
        ]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('billing.grace_days_remaining', fn ($v) => $v >= 6 && $v <= 7)
        );
    }

    public function test_grace_days_remaining_null_for_non_grace_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'active',
            'grace_period_ends_at' => null,
        ]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('billing.grace_days_remaining', null)
        );
    }
}
