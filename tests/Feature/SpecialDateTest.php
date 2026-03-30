<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Models\RestaurantSpecialDate;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SpecialDateTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── Admin CRUD Tests ────────────────────────────────────────────────────

    public function test_admin_can_view_schedules_page_with_special_dates(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        RestaurantSpecialDate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'date' => '2026-12-25',
            'type' => 'closed',
            'label' => 'Navidad',
        ]);

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.schedules'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Schedules')
            ->has('specialDates', 1)
        );
    }

    public function test_admin_can_create_holiday(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('special-dates.store'), [
            'date' => '2026-12-25',
            'type' => 'closed',
            'label' => 'Navidad',
            'is_recurring' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurant_special_dates', [
            'restaurant_id' => $restaurant->id,
            'date' => '2026-12-25',
            'type' => 'closed',
            'label' => 'Navidad',
            'is_recurring' => true,
            'opens_at' => null,
        ]);
    }

    public function test_admin_can_create_special_hours(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('special-dates.store'), [
            'date' => '2026-12-24',
            'type' => 'special',
            'opens_at' => '10:00',
            'closes_at' => '15:00',
            'label' => 'Nochebuena',
            'is_recurring' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurant_special_dates', [
            'restaurant_id' => $restaurant->id,
            'date' => '2026-12-24',
            'type' => 'special',
            'opens_at' => '10:00:00',
            'closes_at' => '15:00:00',
        ]);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        RestaurantSpecialDate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'date' => '2026-12-25',
        ]);

        $response = $this->withoutVite()->actingAs($user)->post(route('special-dates.store'), [
            'date' => '2026-12-25',
            'type' => 'closed',
        ]);

        $response->assertSessionHasErrors('date');
    }

    public function test_special_hours_requires_times(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('special-dates.store'), [
            'date' => '2026-12-24',
            'type' => 'special',
            'opens_at' => null,
            'closes_at' => null,
        ]);

        $response->assertSessionHasErrors(['opens_at', 'closes_at']);
    }

    public function test_admin_can_update_special_date(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $sd = RestaurantSpecialDate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'date' => '2026-12-25',
            'type' => 'closed',
            'label' => 'Navidad',
        ]);

        $response = $this->withoutVite()->actingAs($user)->put(route('special-dates.update', $sd), [
            'date' => '2026-12-25',
            'type' => 'special',
            'opens_at' => '09:00',
            'closes_at' => '14:00',
            'label' => 'Navidad — horario reducido',
            'is_recurring' => true,
        ]);

        $response->assertRedirect();
        $sd->refresh();
        $this->assertEquals('special', $sd->type);
        $this->assertEquals('09:00:00', $sd->opens_at);
    }

    public function test_admin_can_delete_special_date(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $sd = RestaurantSpecialDate::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->delete(route('special-dates.destroy', $sd));

        $response->assertRedirect();
        $this->assertDatabaseMissing('restaurant_special_dates', ['id' => $sd->id]);
    }

    public function test_admin_cannot_access_other_tenant_special_date(): void
    {
        [$user] = $this->createAdminWithRestaurant();
        $otherRestaurant = Restaurant::factory()->create();
        $sd = RestaurantSpecialDate::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->delete(route('special-dates.destroy', $sd));

        $response->assertNotFound();
    }

    // ─── isCurrentlyOpen priority chain ──────────────────────────────────────

    public function test_holiday_overrides_regular_schedule(): void
    {
        $restaurant = Restaurant::factory()->create();

        // Regular schedule says open today.
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        // But today is a holiday.
        RestaurantSpecialDate::factory()->holiday('Test Holiday')->create([
            'restaurant_id' => $restaurant->id,
            'date' => now()->toDateString(),
        ]);

        $restaurant->load('schedules');
        $this->assertFalse($restaurant->isCurrentlyOpen());
    }

    public function test_special_hours_overrides_regular_schedule(): void
    {
        $restaurant = Restaurant::factory()->create();

        // Regular schedule says open all day.
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        // But today has special hours 10:00–11:00.
        RestaurantSpecialDate::factory()->specialHours('10:00', '11:00')->create([
            'restaurant_id' => $restaurant->id,
            'date' => now()->toDateString(),
        ]);

        $restaurant->load('schedules');

        // If now is outside 10:00–11:00, should be closed.
        Carbon::setTestNow(Carbon::today()->setTime(8, 0));
        $this->assertFalse($restaurant->isCurrentlyOpen());

        Carbon::setTestNow(Carbon::today()->setTime(10, 30));
        $this->assertTrue($restaurant->isCurrentlyOpen());

        Carbon::setTestNow(Carbon::today()->setTime(12, 0));
        $this->assertFalse($restaurant->isCurrentlyOpen());

        Carbon::setTestNow(null);
    }

    public function test_recurring_match_by_month_and_day(): void
    {
        $restaurant = Restaurant::factory()->create();

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        // Recurring holiday set for a different year but same month/day.
        RestaurantSpecialDate::factory()->holiday('Annual Holiday')->recurring()->create([
            'restaurant_id' => $restaurant->id,
            'date' => now()->subYear()->toDateString(),
        ]);

        $restaurant->load('schedules');
        $this->assertFalse($restaurant->isCurrentlyOpen());
    }

    public function test_no_special_date_falls_back_to_regular(): void
    {
        $restaurant = Restaurant::factory()->create();

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $restaurant->load('schedules');
        $this->assertTrue($restaurant->isCurrentlyOpen());
    }

    public function test_overnight_special_hours(): void
    {
        $restaurant = Restaurant::factory()->create();

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'is_closed' => false,
        ]);

        // Special schedule: bar night 20:00–03:00.
        RestaurantSpecialDate::factory()->specialHours('20:00', '03:00')->create([
            'restaurant_id' => $restaurant->id,
            'date' => now()->toDateString(),
        ]);

        $restaurant->load('schedules');

        Carbon::setTestNow(Carbon::today()->setTime(21, 0));
        $this->assertTrue($restaurant->isCurrentlyOpen());

        Carbon::setTestNow(Carbon::today()->setTime(15, 0));
        $this->assertFalse($restaurant->isCurrentlyOpen());

        Carbon::setTestNow(null);
    }

    // ─── API response ────────────────────────────────────────────────────────

    public function test_api_returns_closure_reason_for_holiday(): void
    {
        $restaurant = Restaurant::factory()->create([
            'access_token' => 'holiday-api-test',
            'is_active' => true,
        ]);

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        RestaurantSpecialDate::factory()->holiday('Navidad')->create([
            'restaurant_id' => $restaurant->id,
            'date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/restaurant', [
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertFalse($data['is_open']);
        $this->assertEquals('holiday', $data['closure_reason']);
        $this->assertEquals('Navidad', $data['closure_label']);
        $this->assertEquals('closed', $data['today_schedule']['source']);
    }

    // ─── OrderService: scheduled_at on holiday ───────────────────────────────

    public function test_scheduled_order_on_holiday_is_rejected(): void
    {
        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => 1.5, 'duration_minutes' => 5],
        ]);
        $this->instance(GoogleMapsService::class, $mock);

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'order-holiday-test',
            'is_active' => true,
            'orders_limit' => 100,
            'allows_pickup' => true,
        ]);

        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        // Regular schedule open all week.
        for ($d = 0; $d <= 6; $d++) {
            RestaurantSchedule::factory()->create([
                'restaurant_id' => $restaurant->id,
                'day_of_week' => $d,
                'opens_at' => '00:00',
                'closes_at' => '23:59',
                'is_closed' => false,
            ]);
        }

        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price' => 50, 'is_active' => true]);

        // Holiday tomorrow.
        $tomorrow = now()->addDay();
        RestaurantSpecialDate::factory()->holiday('Inventario')->create([
            'restaurant_id' => $restaurant->id,
            'date' => $tomorrow->toDateString(),
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'cust-1', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'scheduled_at' => $tomorrow->setTime(12, 0)->toIso8601String(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
                'modifiers' => [],
            ]],
        ], ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_scheduled_order_outside_special_hours_is_rejected(): void
    {
        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => 1.5, 'duration_minutes' => 5],
        ]);
        $this->instance(GoogleMapsService::class, $mock);

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'order-special-test',
            'is_active' => true,
            'orders_limit' => 100,
            'allows_pickup' => true,
        ]);

        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        for ($d = 0; $d <= 6; $d++) {
            RestaurantSchedule::factory()->create([
                'restaurant_id' => $restaurant->id,
                'day_of_week' => $d,
                'opens_at' => '00:00',
                'closes_at' => '23:59',
                'is_closed' => false,
            ]);
        }

        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price' => 30, 'is_active' => true]);

        // Tomorrow has special hours 10:00–14:00.
        $tomorrow = now()->addDay();
        RestaurantSpecialDate::factory()->specialHours('10:00', '14:00')->create([
            'restaurant_id' => $restaurant->id,
            'date' => $tomorrow->toDateString(),
        ]);

        // Schedule at 18:00 — outside special hours.
        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'cust-2', 'name' => 'Test', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'scheduled_at' => $tomorrow->setTime(18, 0)->toIso8601String(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 30.00,
                'modifiers' => [],
            ]],
        ], ['Authorization' => 'Bearer '.$restaurant->access_token]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['scheduled_at']);
    }
}
