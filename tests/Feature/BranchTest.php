<?php

namespace Tests\Feature;

use App\Models\Branch;
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

    public function test_cannot_toggle_last_active_branch(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant(['max_branches' => 3]);
        $branch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $response = $this->withoutVite()->actingAs($user)->patch(route('branches.toggle', $branch->id));

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('error');
        $this->assertTrue($branch->fresh()->is_active);
    }

    public function test_cannot_delete_last_active_branch(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant(['max_branches' => 3]);
        $branch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $response = $this->withoutVite()->actingAs($user)->delete(route('branches.destroy', $branch->id));

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    public function test_can_toggle_active_branch_when_multiple_active(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant(['max_branches' => 3]);
        $branch1 = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $response = $this->withoutVite()->actingAs($user)->patch(route('branches.toggle', $branch1->id));

        $response->assertRedirect(route('branches.index'));
        $this->assertFalse($branch1->fresh()->is_active);
    }

    public function test_can_delete_active_branch_when_multiple_active(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant(['max_branches' => 3]);
        $branch1 = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $response = $this->withoutVite()->actingAs($user)->delete(route('branches.destroy', $branch1->id));

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('branches', ['id' => $branch1->id]);
    }
}
