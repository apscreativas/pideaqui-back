<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchSchedule;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(?array $restaurantAttributes = []): array
    {
        $restaurant = Restaurant::factory()->create($restaurantAttributes);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    public function test_admin_can_view_branches_list(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('branches.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Branches/Index'));
    }

    public function test_admin_can_create_branch(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant(['max_branches' => 3]);

        $response = $this->withoutVite()->actingAs($user)->post(route('branches.store'), [
            'name' => 'Sucursal Centro',
            'address' => 'Av. Reforma 123, CDMX',
            'whatsapp' => '5512345678',
            'latitude' => 19.4326,
            'longitude' => -99.1332,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseHas('branches', [
            'name' => 'Sucursal Centro',
            'restaurant_id' => $restaurant->id,
        ]);

        // Verify 7 default schedules were created
        $branch = Branch::query()->where('name', 'Sucursal Centro')->first();
        $this->assertEquals(7, $branch->schedules()->count());
    }

    public function test_admin_cannot_exceed_max_branches(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant(['max_branches' => 1]);

        // Create the one allowed branch
        Branch::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->post(route('branches.store'), [
            'name' => 'Sucursal Extra',
            'whatsapp' => '5512345678',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('branches', ['name' => 'Sucursal Extra']);
    }

    public function test_admin_cannot_see_branches_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $otherRestaurant = Restaurant::factory()->create();
        $otherBranch = Branch::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->get(route('branches.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Branches/Index')
            ->where('branches', fn ($branches) => collect($branches)->every(fn ($b) => $b['id'] !== $otherBranch->id))
        );
    }

    public function test_admin_can_update_branch_schedules(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id]);

        // Create schedules for all 7 days
        for ($day = 0; $day <= 6; $day++) {
            BranchSchedule::factory()->create([
                'branch_id' => $branch->id,
                'day_of_week' => $day,
                'opens_at' => '09:00',
                'closes_at' => '21:00',
                'is_closed' => false,
            ]);
        }

        $schedules = collect(range(0, 6))->map(fn ($day) => [
            'day_of_week' => $day,
            'opens_at' => '10:00',
            'closes_at' => '22:00',
            'is_closed' => $day === 0, // Sunday closed
        ])->all();

        $response = $this->withoutVite()->actingAs($user)->put(
            route('branches.schedules.update', $branch->id),
            ['schedules' => $schedules]
        );

        $response->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branch_schedules', [
            'branch_id' => $branch->id,
            'day_of_week' => 1,
            'opens_at' => '10:00',
            'closes_at' => '22:00',
            'is_closed' => false,
        ]);

        $this->assertDatabaseHas('branch_schedules', [
            'branch_id' => $branch->id,
            'day_of_week' => 0,
            'is_closed' => true,
        ]);
    }

    public function test_admin_cannot_update_branch_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $otherRestaurant = Restaurant::factory()->create();
        $otherBranch = Branch::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->put(route('branches.update', $otherBranch->id), [
            'name' => 'Hacked Branch',
            'whatsapp' => '5500000000',
        ]);

        // TenantScope filters out the other restaurant's branch before reaching the Policy,
        // so the model is not found (404), which is the correct and secure behavior.
        $response->assertNotFound();
    }
}
