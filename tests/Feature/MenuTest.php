<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    public function test_admin_can_view_menu_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('menu.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Menu/Index'));
    }

    public function test_admin_can_create_category(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('categories.store'), [
            'name' => 'Tacos',
            'description' => 'Deliciosos tacos',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('menu.index'));
        $this->assertDatabaseHas('categories', [
            'name' => 'Tacos',
            'restaurant_id' => $restaurant->id,
        ]);
    }

    public function test_admin_cannot_see_categories_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $otherRestaurant = Restaurant::factory()->create();
        $otherCategory = Category::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->get(route('menu.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Menu/Index')
            ->where('categories', fn ($categories) => collect($categories)->every(fn ($c) => $c['id'] !== $otherCategory->id))
        );
    }

    public function test_admin_can_create_product_with_image(): void
    {
        Storage::fake('public');

        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        $image = UploadedFile::fake()->image('product.jpg', 200, 200);

        $response = $this->withoutVite()->actingAs($user)->post(route('products.store'), [
            'name' => 'Taco al Pastor',
            'price' => 25.00,
            'category_id' => $category->id,
            'is_active' => true,
            'sort_order' => 0,
            'image' => $image,
        ]);

        $response->assertRedirect(route('menu.index'));

        $product = Product::query()->where('name', 'Taco al Pastor')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_admin_can_toggle_product_is_active(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->withoutVite()->actingAs($user)->patch(route('products.toggle', $product->id));

        $response->assertRedirect(route('menu.index'));
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_admin_can_delete_product(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
        ]);

        $response = $this->withoutVite()->actingAs($user)->delete(route('products.destroy', $product->id));

        $response->assertRedirect(route('menu.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_can_create_modifier_group(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('modifiers.store'), [
            'name' => 'Elige tu tortilla',
            'selection_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('modifier_groups', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Elige tu tortilla',
            'selection_type' => 'single',
        ]);
    }

    public function test_admin_can_create_modifier_option(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $group = ModifierGroup::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->post(route('modifiers.options.store', $group->id), [
            'name' => 'Tortilla de maíz',
            'price_adjustment' => 0,
            'sort_order' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('modifier_options', [
            'modifier_group_id' => $group->id,
            'name' => 'Tortilla de maíz',
        ]);
    }

    public function test_modifier_group_can_be_shared_between_products(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        $product1 = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
        ]);
        $product2 = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
        ]);

        $group = ModifierGroup::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->withoutVite()->actingAs($user)->put(route('products.update', $product1->id), [
            'name' => $product1->name,
            'price' => $product1->price,
            'category_id' => $category->id,
            'modifier_group_ids' => [$group->id],
        ]);

        $this->withoutVite()->actingAs($user)->put(route('products.update', $product2->id), [
            'name' => $product2->name,
            'price' => $product2->price,
            'category_id' => $category->id,
            'modifier_group_ids' => [$group->id],
        ]);

        $this->assertCount(1, $product1->fresh()->modifierGroups);
        $this->assertCount(1, $product2->fresh()->modifierGroups);
        $this->assertEquals($group->id, $product1->fresh()->modifierGroups->first()->id);
        $this->assertEquals($group->id, $product2->fresh()->modifierGroups->first()->id);
    }

    public function test_admin_cannot_manage_products_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $otherRestaurant = Restaurant::factory()->create();
        $otherCategory = Category::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $otherProduct = Product::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'category_id' => $otherCategory->id,
        ]);

        $response = $this->withoutVite()->actingAs($user)->patch(route('products.toggle', $otherProduct->id));

        // TenantScope filters out the other restaurant's product before reaching the Policy,
        // so the model is not found (404), which is the correct and secure behavior.
        $response->assertNotFound();
    }
}
