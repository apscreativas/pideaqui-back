<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── Admin CRUD ────────────────────────────────────────────────────────────

    public function test_admin_can_view_promotions_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('promotions.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Promotions/Index'));
    }

    public function test_admin_can_create_promotion(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('promotions.store'), [
            'name' => '3 Tacos por $70',
            'description' => 'Promo de lunes a viernes',
            'price' => 70.00,
            'production_cost' => 30.00,
            'is_active' => true,
            'active_days' => [1, 2, 3, 4, 5],
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ]);

        $response->assertRedirect(route('promotions.index'));
        $this->assertDatabaseHas('promotions', [
            'name' => '3 Tacos por $70',
            'restaurant_id' => $restaurant->id,
            'price' => 70.00,
            'production_cost' => 30.00,
        ]);
    }

    public function test_admin_can_create_promotion_with_image(): void
    {
        Storage::fake('public');

        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('promotions.store'), [
            'name' => 'Combo Familiar',
            'price' => 150.00,
            'image' => UploadedFile::fake()->image('promo.jpg', 200, 200),
            'is_active' => true,
            'active_days' => [0, 1, 2, 3, 4, 5, 6],
        ]);

        $response->assertRedirect(route('promotions.index'));
        $promo = Promotion::where('name', 'Combo Familiar')->first();
        $this->assertNotNull($promo->image_path);
        Storage::disk('public')->assertExists($promo->image_path);
    }

    public function test_admin_can_update_promotion(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $promotion = Promotion::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->put(route('promotions.update', $promotion), [
            'name' => 'Promo Actualizada',
            'price' => 49.99,
            'is_active' => true,
            'active_days' => [0, 6],
            'starts_at' => '10:00',
            'ends_at' => '14:00',
        ]);

        $response->assertRedirect(route('promotions.index'));
        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Promo Actualizada',
            'price' => 49.99,
        ]);
    }

    public function test_admin_can_delete_promotion(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $promotion = Promotion::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->delete(route('promotions.destroy', $promotion));

        $response->assertRedirect(route('promotions.index'));
        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }

    public function test_admin_can_toggle_promotion(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $promotion = Promotion::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => false,
        ]);

        $this->withoutVite()->actingAs($user)->patch(route('promotions.toggle', $promotion));

        $this->assertTrue($promotion->fresh()->is_active);
    }

    public function test_admin_cannot_see_promotions_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $otherRestaurant = Restaurant::factory()->create();
        Promotion::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->get(route('promotions.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Promotions/Index')
            ->where('promotions', fn ($promos) => collect($promos)->isEmpty())
        );
    }

    // ─── Validation ────────────────────────────────────────────────────────────

    public function test_promotion_requires_name_and_price(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('promotions.store'), [
            'active_days' => [1],
        ]);

        $response->assertSessionHasErrors(['name', 'price']);
    }

    public function test_promotion_requires_at_least_one_day(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('promotions.store'), [
            'name' => 'Sin Dias',
            'price' => 50.00,
            'active_days' => [],
        ]);

        $response->assertSessionHasErrors('active_days');
    }

    // ─── Vigencia (isCurrentlyActive) ──────────────────────────────────────────

    public function test_promotion_is_active_within_schedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 14, 0, 0)); // Tuesday 14:00

        $promotion = Promotion::factory()->create([
            'is_active' => true,
            'active_days' => [2],
            'starts_at' => '12:00',
            'ends_at' => '18:00',
        ]);

        $this->assertTrue($promotion->isCurrentlyActive());
    }

    public function test_promotion_is_inactive_outside_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 20, 0, 0)); // Tuesday 20:00

        $promotion = Promotion::factory()->create([
            'is_active' => true,
            'active_days' => [2],
            'starts_at' => '12:00',
            'ends_at' => '18:00',
        ]);

        $this->assertFalse($promotion->isCurrentlyActive());
    }

    public function test_promotion_is_inactive_on_wrong_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 18, 14, 0, 0)); // Wednesday 14:00

        $promotion = Promotion::factory()->create([
            'is_active' => true,
            'active_days' => [2],
            'starts_at' => '12:00',
            'ends_at' => '18:00',
        ]);

        $this->assertFalse($promotion->isCurrentlyActive());
    }

    public function test_promotion_is_inactive_when_disabled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 14, 0, 0));

        $promotion = Promotion::factory()->create([
            'is_active' => false,
            'active_days' => [2],
            'starts_at' => '12:00',
            'ends_at' => '18:00',
        ]);

        $this->assertFalse($promotion->isCurrentlyActive());
    }

    public function test_promotion_overnight_schedule_works(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 23, 30, 0)); // Tuesday 23:30

        $promotion = Promotion::factory()->create([
            'is_active' => true,
            'active_days' => [2],
            'starts_at' => '22:00',
            'ends_at' => '02:00',
        ]);

        $this->assertTrue($promotion->isCurrentlyActive());
    }

    public function test_promotion_all_day_when_no_times(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 3, 0, 0)); // Tuesday 03:00

        $promotion = Promotion::factory()->create([
            'is_active' => true,
            'active_days' => [2],
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertTrue($promotion->isCurrentlyActive());
    }

    // ─── API: Promotions in menu ───────────────────────────────────────────────

    public function test_api_menu_shows_promotion_category_when_active(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 14, 0, 0)); // Tuesday

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'test-token',
            'is_active' => true,
        ]);

        Promotion::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => '3 Tacos por $70',
            'price' => 70.00,
            'is_active' => true,
            'active_days' => [2],
            'starts_at' => '10:00',
            'ends_at' => '18:00',
        ]);

        $response = $this->getJson('/api/menu', ['Authorization' => 'Bearer '.$restaurant->access_token]);
        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals('Promociones', $data[0]['name']);
        $this->assertTrue($data[0]['is_promotion_category']);
        $this->assertCount(1, $data[0]['products']);
        $this->assertEquals(70.00, $data[0]['products'][0]['price']);
        $this->assertEquals('3 Tacos por $70', $data[0]['products'][0]['name']);
        $this->assertTrue($data[0]['products'][0]['is_promotion']);
    }

    public function test_api_menu_hides_promotion_category_when_outside_schedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 18, 14, 0, 0)); // Wednesday

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'test-token',
            'is_active' => true,
        ]);

        Promotion::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
            'active_days' => [2],
            'starts_at' => '10:00',
            'ends_at' => '18:00',
        ]);

        $response = $this->getJson('/api/menu', ['Authorization' => 'Bearer '.$restaurant->access_token]);
        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $cat) {
            $this->assertNotEquals('Promociones', $cat['name']);
        }
    }

    public function test_api_menu_without_promotions_works_normally(): void
    {
        $restaurant = Restaurant::factory()->create([
            'access_token' => 'test-token',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/menu', ['Authorization' => 'Bearer '.$restaurant->access_token]);
        $response->assertOk();
    }
}
