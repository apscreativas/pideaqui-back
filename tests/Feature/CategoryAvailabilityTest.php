<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CategoryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── isCurrentlyAvailable() ────────────────────────────────────────────────

    public function test_category_without_schedule_is_always_available(): void
    {
        $category = Category::factory()->create([
            'is_active' => true,
            'available_days' => null,
            'available_from' => null,
            'available_until' => null,
        ]);

        $this->assertTrue($category->isCurrentlyAvailable());
    }

    public function test_category_with_schedule_available_on_correct_day_and_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 10, 0, 0)); // Tuesday 10:00

        $category = Category::factory()->create([
            'is_active' => true,
            'available_days' => [1, 2, 3, 4, 5],
            'available_from' => '07:00',
            'available_until' => '12:00',
        ]);

        $this->assertTrue($category->isCurrentlyAvailable());
    }

    public function test_category_with_schedule_unavailable_outside_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 15, 0, 0)); // Tuesday 15:00

        $category = Category::factory()->create([
            'is_active' => true,
            'available_days' => [1, 2, 3, 4, 5],
            'available_from' => '07:00',
            'available_until' => '12:00',
        ]);

        $this->assertFalse($category->isCurrentlyAvailable());
    }

    public function test_category_with_schedule_unavailable_on_wrong_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 21, 10, 0, 0)); // Saturday 10:00

        $category = Category::factory()->create([
            'is_active' => true,
            'available_days' => [1, 2, 3, 4, 5],
            'available_from' => '07:00',
            'available_until' => '12:00',
        ]);

        $this->assertFalse($category->isCurrentlyAvailable());
    }

    public function test_category_inactive_is_not_available(): void
    {
        $category = Category::factory()->create([
            'is_active' => false,
            'available_days' => null,
        ]);

        $this->assertFalse($category->isCurrentlyAvailable());
    }

    public function test_category_overnight_schedule_works(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 23, 30, 0)); // Tuesday 23:30

        $category = Category::factory()->create([
            'is_active' => true,
            'available_days' => [2],
            'available_from' => '20:00',
            'available_until' => '02:00',
        ]);

        $this->assertTrue($category->isCurrentlyAvailable());
    }

    public function test_category_days_only_no_time_restriction(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 3, 0, 0)); // Tuesday 03:00

        $category = Category::factory()->create([
            'is_active' => true,
            'available_days' => [2],
            'available_from' => null,
            'available_until' => null,
        ]);

        $this->assertTrue($category->isCurrentlyAvailable());
    }

    // ─── API filtering ─────────────────────────────────────────────────────────

    public function test_api_menu_filters_categories_by_availability(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 15, 0, 0)); // Tuesday 15:00

        $restaurant = Restaurant::factory()->create([
            'is_active' => true,
        ]);

        $alwaysCategory = Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
            'available_days' => null,
            'sort_order' => 0,
        ]);
        Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $alwaysCategory->id,
            'is_active' => true,
        ]);

        $breakfastCategory = Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
            'available_days' => [1, 2, 3, 4, 5],
            'available_from' => '07:00',
            'available_until' => '12:00',
            'sort_order' => 1,
        ]);
        Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $breakfastCategory->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/public/{$restaurant->slug}/menu");
        $response->assertOk();

        $data = $response->json('data');
        $categoryIds = collect($data)->pluck('id')->filter()->toArray();

        $this->assertContains($alwaysCategory->id, $categoryIds);
        $this->assertNotContains($breakfastCategory->id, $categoryIds);
    }

    public function test_api_menu_shows_category_within_schedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 10, 0, 0)); // Tuesday 10:00

        $restaurant = Restaurant::factory()->create([
            'is_active' => true,
        ]);

        $breakfastCategory = Category::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
            'available_days' => [1, 2, 3, 4, 5],
            'available_from' => '07:00',
            'available_until' => '12:00',
        ]);
        Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $breakfastCategory->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/public/{$restaurant->slug}/menu");
        $response->assertOk();

        $categoryIds = collect($response->json('data'))->pluck('id')->filter()->toArray();
        $this->assertContains($breakfastCategory->id, $categoryIds);
    }

    // ─── Admin: category availability fields ──────────────────────────────────

    public function test_admin_can_create_category_with_availability(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('categories.store'), [
            'name' => 'Desayunos',
            'is_active' => true,
            'available_days' => [1, 2, 3, 4, 5],
            'available_from' => '07:00',
            'available_until' => '12:00',
        ]);

        $response->assertRedirect(route('menu.index'));
        $this->assertDatabaseHas('categories', [
            'name' => 'Desayunos',
            'restaurant_id' => $restaurant->id,
        ]);

        $category = Category::where('name', 'Desayunos')->first();
        $this->assertEquals([1, 2, 3, 4, 5], $category->available_days);
    }

    public function test_admin_can_create_category_without_availability(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('categories.store'), [
            'name' => 'Siempre Visible',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('menu.index'));

        $category = Category::where('name', 'Siempre Visible')->first();
        $this->assertNull($category->available_days);
        $this->assertNull($category->available_from);
        $this->assertNull($category->available_until);
    }
}
