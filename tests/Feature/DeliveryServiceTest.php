<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DeliveryRange;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(array $attributes = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'is_active' => true,
        ], $attributes));
    }

    private function branchAt(Restaurant $restaurant, float $lat, float $lng, bool $active = true): Branch
    {
        return Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'is_active' => $active,
        ]);
    }

    private function addRanges(Restaurant $restaurant): void
    {
        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 3,
            'price' => 0,
            'sort_order' => 1,
        ]);
        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 3,
            'max_km' => 8,
            'price' => 30,
            'sort_order' => 2,
        ]);
    }

    private function mockGoogleMaps(float $distanceKm = 0.5, int $durationMinutes = 3): void
    {
        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => $distanceKm, 'duration_minutes' => $durationMinutes],
        ]);
        $this->instance(GoogleMapsService::class, $mock);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_missing_coordinates_returns_422(): void
    {
        $restaurant = $this->restaurant();

        $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_invalid_latitude_returns_422(): void
    {
        $restaurant = $this->restaurant();

        $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 91,
            'longitude' => -99,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->postJson('/api/public/ghost-slug/delivery/calculate', [
            'latitude' => 20.0,
            'longitude' => -99.0,
        ])->assertNotFound()
            ->assertJsonPath('code', 'tenant_not_found');
    }

    // ─── Single branch (Google Maps driving distance) ──────────────────────

    public function test_single_branch_uses_google_maps_for_driving_distance(): void
    {
        $this->mockGoogleMaps(0.8, 4); // 0.8 km driving, within 0-3 km free zone

        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609); // Guadalajara
        $this->addRanges($restaurant);

        // Client is very close
        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'branch_id', 'branch_name', 'branch_address', 'branch_whatsapp',
                    'distance_km', 'duration_minutes', 'delivery_cost',
                    'is_in_coverage', 'is_open',
                ],
            ])
            ->assertJsonPath('data.distance_km', 0.8)
            ->assertJsonPath('data.duration_minutes', 4)
            ->assertJsonPath('data.is_in_coverage', true)
            ->assertJsonPath('data.delivery_cost', 0);
    }

    public function test_single_branch_outside_coverage_returns_false(): void
    {
        $this->mockGoogleMaps(500.0, 360); // 500 km driving — far outside max 8 km range

        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant); // Max range is 8 km

        // Client is ~200 km away (Mexico City)
        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 19.432608,
            'longitude' => -99.133209,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_in_coverage', false);
    }

    // ─── Multiple branches (Google Distance Matrix called) ───────────────────

    public function test_multiple_branches_selects_closest_by_haversine_then_google(): void
    {
        $restaurant = $this->restaurant();
        $nearBranch = $this->branchAt($restaurant, 20.659698, -103.349609);
        $farBranch = $this->branchAt($restaurant, 20.700000, -103.400000);
        $this->addRanges($restaurant);

        // Only 1 candidate (the Haversine-closest) is sent to Google Maps
        $mockGoogle = $this->createMock(GoogleMapsService::class);
        $mockGoogle->method('getDistances')->willReturn([
            ['distance_km' => 1.5, 'duration_minutes' => 5],
        ]);
        $this->instance(GoogleMapsService::class, $mockGoogle);

        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.branch_id', $nearBranch->id)
            ->assertJsonPath('data.distance_km', 1.5)
            ->assertJsonPath('data.duration_minutes', 5)
            ->assertJsonPath('data.delivery_cost', 0)   // 0-3 km = free
            ->assertJsonPath('data.is_in_coverage', true);
    }

    public function test_multiple_branches_second_range_cost(): void
    {
        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->branchAt($restaurant, 20.700000, -103.400000);
        $this->addRanges($restaurant);

        $mockGoogle = $this->createMock(GoogleMapsService::class);
        // Only 1 candidate sent to Google Maps, returns 5 km (second range: $30)
        $mockGoogle->method('getDistances')->willReturn([
            ['distance_km' => 5.0, 'duration_minutes' => 12],
        ]);
        $this->instance(GoogleMapsService::class, $mockGoogle);

        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery_cost', 30)
            ->assertJsonPath('data.is_in_coverage', true);
    }

    public function test_inactive_branches_are_excluded(): void
    {
        $this->mockGoogleMaps(0.5, 3);

        $restaurant = $this->restaurant();
        // Only one active branch (the inactive should be ignored)
        $activeBranch = $this->branchAt($restaurant, 20.659698, -103.349609, true);
        $this->branchAt($restaurant, 20.650000, -103.340000, false);
        $this->addRanges($restaurant);

        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.branch_id', $activeBranch->id);
    }

    // ─── Schedule validation ─────────────────────────────────────────────────

    public function test_branch_with_no_schedule_returns_closed(): void
    {
        $this->mockGoogleMaps(0.5, 3);

        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant);

        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertOk()->assertJsonPath('data.is_open', false);
    }

    public function test_branch_with_closed_day_schedule_returns_not_open(): void
    {
        $this->mockGoogleMaps(0.5, 3);

        $restaurant = $this->restaurant();
        $branch = $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant);

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
            'is_closed' => true,
        ]);

        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_open', false)
            ->assertJsonStructure(['data' => ['schedule' => ['day_of_week', 'opens_at', 'closes_at', 'is_closed']]]);
    }

    public function test_google_maps_failure_returns_error_without_fallback(): void
    {
        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant);

        $mockGoogle = $this->createMock(GoogleMapsService::class);
        $mockGoogle->method('getDistances')->willThrowException(new \RuntimeException('Google Maps unavailable'));
        $this->instance(GoogleMapsService::class, $mockGoogle);

        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertStatus(422);
    }

    public function test_google_maps_max_float_returns_error(): void
    {
        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant);

        $mockGoogle = $this->createMock(GoogleMapsService::class);
        $mockGoogle->method('getDistances')->willReturn([
            ['distance_km' => PHP_FLOAT_MAX, 'duration_minutes' => 0],
        ]);
        $this->instance(GoogleMapsService::class, $mockGoogle);

        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        $response->assertStatus(422);
    }

    public function test_does_not_return_other_restaurants_branches(): void
    {
        $this->mockGoogleMaps();

        $restaurant = $this->restaurant();
        $otherRestaurant = Restaurant::factory()->create(['is_active' => true]);
        $this->addRanges($restaurant);

        // Only other restaurant has branches — our restaurant has 0 active branches
        $this->branchAt($otherRestaurant, 20.659698, -103.349609);

        // With no branches, DeliveryService will throw or return empty; test expects a handled response
        $response = $this->postJson("/api/public/{$restaurant->slug}/delivery/calculate", [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ]);

        // No active branches → service throws, API returns 422 or 404
        $response->assertStatus(422);
    }
}
