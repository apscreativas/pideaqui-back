<?php

namespace Tests\Feature\Auth;

use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Plan::gracePlan()) {
            Plan::factory()->grace()->create();
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'restaurant_name' => 'Nuevo Taco',
            'admin_name' => 'Ana Pérez',
            'email' => 'ana@nuevotaco.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ], $overrides);
    }

    public function test_register_page_renders(): void
    {
        $response = $this->withoutVite()->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_happy_path_creates_everything_and_redirects_to_verify(): void
    {
        Event::fake();

        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertRedirect(route('verification.notice'));

        $restaurant = Restaurant::where('name', 'Nuevo Taco')->firstOrFail();
        $this->assertEquals('self_signup', $restaurant->signup_source);
        $this->assertEquals('grace_period', $restaurant->status);

        $user = User::where('email', 'ana@nuevotaco.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals($restaurant->id, $user->restaurant_id);

        Event::assertDispatched(Registered::class);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->post(route('register.store'), $this->validPayload([
            'email' => 'existing@test.com',
        ]));

        $response->assertSessionHasErrors('email');
    }

    public function test_rejects_weak_password(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]));

        $response->assertSessionHasErrors('password');
    }

    public function test_normalizes_email_to_lowercase(): void
    {
        $this->post(route('register.store'), $this->validPayload([
            'email' => 'MiXeD@Test.COM',
        ]));

        $this->assertDatabaseHas('users', ['email' => 'mixed@test.com']);
    }

    public function test_logs_billing_audit_with_self_signup_actor(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $restaurant = Restaurant::where('name', 'Nuevo Taco')->firstOrFail();
        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'restaurant_created',
            'actor_type' => 'self_signup',
        ]);
    }

    public function test_seeds_three_payment_methods(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $restaurant = Restaurant::where('name', 'Nuevo Taco')->firstOrFail();
        $this->assertEquals(3, PaymentMethod::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_user_is_logged_in_after_register(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $user = User::where('email', 'ana@nuevotaco.com')->firstOrFail();
        $this->assertTrue(auth()->guard('web')->check());
        $this->assertEquals($user->id, auth()->guard('web')->id());
    }

    public function test_throttled_after_three_attempts(): void
    {
        $response = null;
        for ($i = 0; $i < 4; $i++) {
            $response = $this->post(route('register.store'), $this->validPayload([
                'email' => "attempt{$i}@test.com",
            ]));
            // Logout so next attempt hits the 'guest' middleware and reaches the throttle.
            auth()->guard('web')->logout();
        }

        $response->assertStatus(429);
    }

    public function test_self_signup_sends_custom_spanish_verification_notification(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->post(route('register.store'), $this->validPayload());

        $user = User::where('email', 'ana@nuevotaco.com')->firstOrFail();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $user,
            \App\Notifications\VerifyEmailNotification::class,
            function ($notification) use ($user) {
                $mail = $notification->toMail($user);
                $data = $mail->toArray();

                $this->assertEquals('Verifica tu correo — PideAqui', $mail->subject);
                $this->assertEquals('¡Bienvenido a PideAqui!', $data['greeting']);
                $this->assertEquals('Verificar mi correo', $data['actionText']);

                return true;
            },
        );
    }

    public function test_authenticated_user_redirected_away_from_register(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('register'));

        // Guest middleware redirects authenticated users.
        $response->assertRedirect();
    }
}
