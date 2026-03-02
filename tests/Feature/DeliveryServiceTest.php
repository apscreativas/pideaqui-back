<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\DeliveryRange;
use App\Models\Restaurant;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(array $attributes = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'access_token' => 'test-token-del',
            'is_active' => true,
        ], $attributes));
    }

    private function authHeaders(Restaurant $restaurant): array
    {
        return ['Authorization' => 'Bearer '.$restaurant->access_token];
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

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_missing_coordinates_returns_422(): void
    {
        $restaurant = $this->restaurant();

        $this->postJson('/api/delivery/calculate', [], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_invalid_latitude_returns_422(): void
    {
        $restaurant = $this->restaurant();

        $this->postJson('/api/delivery/calculate', [
            'latitude' => 91,
            'longitude' => -99,
        ], $this->authHeaders($restaurant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.0,
            'longitude' => -99.0,
        ])->assertUnauthorized();
    }

    // ─── Single branch (no Google call) ─────────────────────────────────────

    public function test_single_branch_uses_haversine_only(): void
    {
        $this->instance(GoogleMapsService::class, $this->createMock(GoogleMapsService::class));

        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609); // Guadalajara
        $this->addRanges($restaurant);

        // Client is very close (within 3km free zone)
        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ], $this->authHeaders($restaurant));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'branch_id', 'branch_name', 'branch_address', 'branch_whatsapp',
                    'distance_km', 'duration_minutes', 'delivery_cost',
                    'is_in_coverage', 'is_open',
                ],
            ])
            ->assertJsonPath('data.is_in_coverage', true)
            ->assertJsonPath('data.delivery_cost', 0);
    }

    public function test_single_branch_outside_coverage_returns_false(): void
    {
        $this->instance(GoogleMapsService::class, $this->createMock(GoogleMapsService::class));

        $restaurant = $this->restaurant();
        // Branch in Guadalajara
        $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant); // Max range is 8 km

        // Client is ~200 km away (Mexico City)
        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 19.432608,
            'longitude' => -99.133209,
        ], $this->authHeaders($restaurant));

        $response->assertOk()
            ->assertJsonPath('data.is_in_coverage', false);
    }

    // ─── Multiple branches (Google Distance Matrix called) ───────────────────

    public function test_multiple_branches_selects_closest_by_google_distance(): void
    {
        $restaurant = $this->restaurant();
        $nearBranch = $this->branchAt($restaurant, 20.659698, -103.349609);
        $farBranch = $this->branchAt($restaurant, 20.700000, -103.400000);
        $this->addRanges($restaurant);

        $mockGoogle = $this->createMock(GoogleMapsService::class);
        $mockGoogle->method('getDistances')->willReturn([
            ['distance_km' => 1.5, 'duration_minutes' => 5],  // nearBranch
            ['distance_km' => 6.0, 'duration_minutes' => 15], // farBranch
        ]);
        $this->instance(GoogleMapsService::class, $mockGoogle);

        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ], $this->authHeaders($restaurant));

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
        // Closest branch is 5 km away (second range: $30)
        $mockGoogle->method('getDistances')->willReturn([
            ['distance_km' => 5.0, 'duration_minutes' => 12],
            ['distance_km' => 8.5, 'duration_minutes' => 20],
        ]);
        $this->instance(GoogleMapsService::class, $mockGoogle);

        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ], $this->authHeaders($restaurant));

        $response->assertOk()
            ->assertJsonPath('data.delivery_cost', 30)
            ->assertJsonPath('data.is_in_coverage', true);
    }

    public function test_inactive_branches_are_excluded(): void
    {
        $this->instance(GoogleMapsService::class, $this->createMock(GoogleMapsService::class));

        $restaurant = $this->restaurant();
        // Only one active branch (the inactive should be ignored)
        $activeBranch = $this->branchAt($restaurant, 20.659698, -103.349609, true);
        $this->branchAt($restaurant, 20.650000, -103.340000, false);
        $this->addRanges($restaurant);

        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ], $this->authHeaders($restaurant));

        $response->assertOk()
            ->assertJsonPath('data.branch_id', $activeBranch->id);
    }

    // ─── Schedule validation ─────────────────────────────────────────────────

    public function test_branch_with_no_schedule_is_always_open(): void
    {
        $this->instance(GoogleMapsService::class, $this->createMock(GoogleMapsService::class));

        $restaurant = $this->restaurant();
        $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant);

        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ], $this->authHeaders($restaurant));

        $response->assertOk()->assertJsonPath('data.is_open', true);
    }

    public function test_branch_with_closed_day_schedule_returns_not_open(): void
    {
        $this->instance(GoogleMapsService::class, $this->createMock(GoogleMapsService::class));

        $restaurant = $this->restaurant();
        $branch = $this->branchAt($restaurant, 20.659698, -103.349609);
        $this->addRanges($restaurant);

        BranchSchedule::factory()->create([
            'branch_id' => $branch->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
            'is_closed' => true,
        ]);

        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ], $this->authHeaders($restaurant));

        $response->assertOk()
            ->assertJsonPath('data.is_open', false)
            ->assertJsonStructure(['data' => ['schedule' => ['day_of_week', 'opens_at', 'closes_at', 'is_closed']]]);
    }

    public function test_does_not_return_other_restaurants_branches(): void
    {
        $this->instance(GoogleMapsService::class, $this->createMock(GoogleMapsService::class));

        $restaurant = $this->restaurant();
        $otherRestaurant = Restaurant::factory()->create(['is_active' => true]);
        $this->addRanges($restaurant);

        // Only other restaurant has branches — our restaurant has 0 active branches
        $this->branchAt($otherRestaurant, 20.659698, -103.349609);

        // With no branches, DeliveryService will throw or return empty; test expects a handled response
        $response = $this->postJson('/api/delivery/calculate', [
            'latitude' => 20.660000,
            'longitude' => -103.350000,
        ], $this->authHeaders($restaurant));

        // No active branches → service throws, API returns 422 or 404
        $response->assertStatus(422);
    }
}
