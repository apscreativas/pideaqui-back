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

    public function test_admin_can_create_product_with_inline_modifier_groups(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->post(route('products.store'), [
            'name' => 'Taco al Pastor',
            'price' => 25.00,
            'category_id' => $category->id,
            'is_active' => true,
            'sort_order' => 0,
            'modifier_groups' => [
                [
                    'name' => 'Elige tu tortilla',
                    'selection_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        ['name' => 'Maíz', 'price_adjustment' => 0, 'production_cost' => 1.50],
                        ['name' => 'Harina', 'price_adjustment' => 5, 'production_cost' => 2.00],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('menu.index'));

        $product = Product::query()->where('name', 'Taco al Pastor')->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->modifierGroups);

        $group = $product->modifierGroups->first();
        $this->assertEquals('Elige tu tortilla', $group->name);
        $this->assertEquals('single', $group->selection_type);
        $this->assertTrue($group->is_required);
        $this->assertEquals($restaurant->id, $group->restaurant_id);
        $this->assertCount(2, $group->options);
        $this->assertEquals('1.50', $group->options->first()->production_cost);
    }

    public function test_admin_can_edit_product_modifier_groups(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
        ]);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'Extras',
        ]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'name' => 'Queso',
            'price_adjustment' => 10,
        ]);

        $response = $this->withoutVite()->actingAs($user)->put(route('products.update', $product->id), [
            'name' => $product->name,
            'price' => $product->price,
            'category_id' => $category->id,
            'modifier_groups' => [
                [
                    'id' => $group->id,
                    'name' => 'Extras Actualizados',
                    'selection_type' => 'multiple',
                    'is_required' => false,
                    'options' => [
                        ['id' => $option->id, 'name' => 'Queso Extra', 'price_adjustment' => 15, 'production_cost' => 3],
                        ['name' => 'Guacamole', 'price_adjustment' => 20, 'production_cost' => 5],
                    ],
                ],
                [
                    'name' => 'Nuevo Grupo',
                    'selection_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        ['name' => 'Opción A', 'price_adjustment' => 0],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('menu.index'));

        $product->refresh();
        $this->assertCount(2, $product->modifierGroups);

        $updatedGroup = $product->modifierGroups->where('id', $group->id)->first();
        $this->assertEquals('Extras Actualizados', $updatedGroup->name);
        $this->assertCount(2, $updatedGroup->options);
        $this->assertEquals('Queso Extra', $updatedGroup->options->where('id', $option->id)->first()->name);

        $newGroup = $product->modifierGroups->where('name', 'Nuevo Grupo')->first();
        $this->assertNotNull($newGroup);
        $this->assertCount(1, $newGroup->options);
    }

    public function test_removing_modifier_groups_on_update_deletes_them(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
        ]);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
        ]);
        ModifierOption::factory()->create(['modifier_group_id' => $group->id]);

        $response = $this->withoutVite()->actingAs($user)->put(route('products.update', $product->id), [
            'name' => $product->name,
            'price' => $product->price,
            'category_id' => $category->id,
            'modifier_groups' => [],
        ]);

        $response->assertRedirect(route('menu.index'));
        $this->assertDatabaseMissing('modifier_groups', ['id' => $group->id]);
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

    // ── Category auto sort_order ──

    public function test_category_sort_order_auto_assigned_on_create(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        // Create first category — should get sort_order 1
        $this->withoutVite()->actingAs($user)->post(route('categories.store'), [
            'name' => 'Primera',
            'is_active' => true,
        ]);

        $first = Category::query()->where('name', 'Primera')->first();
        $this->assertEquals(1, $first->sort_order);

        // Create second category — should get sort_order 2
        $this->withoutVite()->actingAs($user)->post(route('categories.store'), [
            'name' => 'Segunda',
            'is_active' => true,
        ]);

        $second = Category::query()->where('name', 'Segunda')->first();
        $this->assertEquals(2, $second->sort_order);
    }

    public function test_category_auto_sort_order_scoped_to_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        // Another restaurant has categories with high sort_order
        $otherRestaurant = Restaurant::factory()->create();
        Category::factory()->create(['restaurant_id' => $otherRestaurant->id, 'sort_order' => 99]);

        // Our category should still get sort_order 1
        $this->withoutVite()->actingAs($user)->post(route('categories.store'), [
            'name' => 'Mi Categoría',
            'is_active' => true,
        ]);

        $cat = Category::query()->where('name', 'Mi Categoría')->first();
        $this->assertEquals(1, $cat->sort_order);
    }

    // ── Category reorder ──

    public function test_admin_can_reorder_categories(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $catA = Category::factory()->create(['restaurant_id' => $restaurant->id, 'sort_order' => 0, 'name' => 'A']);
        $catB = Category::factory()->create(['restaurant_id' => $restaurant->id, 'sort_order' => 1, 'name' => 'B']);
        $catC = Category::factory()->create(['restaurant_id' => $restaurant->id, 'sort_order' => 2, 'name' => 'C']);

        // Reverse order: C, B, A
        $response = $this->withoutVite()->actingAs($user)->patch(route('categories.reorder'), [
            'ids' => [$catC->id, $catB->id, $catA->id],
        ]);

        $response->assertRedirect(route('menu.index'));

        $this->assertEquals(0, $catC->fresh()->sort_order);
        $this->assertEquals(1, $catB->fresh()->sort_order);
        $this->assertEquals(2, $catA->fresh()->sort_order);
    }

    public function test_category_reorder_only_affects_own_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $ownCat = Category::factory()->create(['restaurant_id' => $restaurant->id, 'sort_order' => 0]);

        $otherRestaurant = Restaurant::factory()->create();
        $otherCat = Category::factory()->create(['restaurant_id' => $otherRestaurant->id, 'sort_order' => 5]);

        // Try to reorder including the other restaurant's category
        $this->withoutVite()->actingAs($user)->patch(route('categories.reorder'), [
            'ids' => [$otherCat->id, $ownCat->id],
        ]);

        // Other restaurant's category should remain unchanged
        $this->assertEquals(5, $otherCat->fresh()->sort_order);
        // Own category gets index 1 (second in array)
        $this->assertEquals(1, $ownCat->fresh()->sort_order);
    }

    public function test_category_reorder_requires_ids(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->patch(route('categories.reorder'), []);

        $response->assertSessionHasErrors('ids');
    }

    // ── Product auto sort_order ──

    public function test_product_sort_order_auto_assigned_on_create(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->withoutVite()->actingAs($user)->post(route('products.store'), [
            'name' => 'Producto 1',
            'price' => 10,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $p1 = Product::query()->where('name', 'Producto 1')->first();
        $this->assertEquals(1, $p1->sort_order);

        $this->withoutVite()->actingAs($user)->post(route('products.store'), [
            'name' => 'Producto 2',
            'price' => 20,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $p2 = Product::query()->where('name', 'Producto 2')->first();
        $this->assertEquals(2, $p2->sort_order);
    }

    public function test_product_auto_sort_order_scoped_to_category(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $catA = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $catB = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        // catA has a product with high sort_order
        Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $catA->id,
            'sort_order' => 50,
        ]);

        // New product in catB should get sort_order 1 (not 51)
        $this->withoutVite()->actingAs($user)->post(route('products.store'), [
            'name' => 'Nuevo en B',
            'price' => 15,
            'category_id' => $catB->id,
            'is_active' => true,
        ]);

        $product = Product::query()->where('name', 'Nuevo en B')->first();
        $this->assertEquals(1, $product->sort_order);
    }

    // ── Product reorder ──

    public function test_admin_can_reorder_products(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        $pA = Product::factory()->create(['restaurant_id' => $restaurant->id, 'category_id' => $category->id, 'sort_order' => 0]);
        $pB = Product::factory()->create(['restaurant_id' => $restaurant->id, 'category_id' => $category->id, 'sort_order' => 1]);
        $pC = Product::factory()->create(['restaurant_id' => $restaurant->id, 'category_id' => $category->id, 'sort_order' => 2]);

        // Reverse: C, A, B
        $response = $this->withoutVite()->actingAs($user)->patch(route('products.reorder'), [
            'ids' => [$pC->id, $pA->id, $pB->id],
        ]);

        $response->assertRedirect(route('menu.index'));

        $this->assertEquals(0, $pC->fresh()->sort_order);
        $this->assertEquals(1, $pA->fresh()->sort_order);
        $this->assertEquals(2, $pB->fresh()->sort_order);
    }

    public function test_product_reorder_only_affects_own_restaurant(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
        $ownProduct = Product::factory()->create(['restaurant_id' => $restaurant->id, 'category_id' => $category->id, 'sort_order' => 0]);

        $otherRestaurant = Restaurant::factory()->create();
        $otherCategory = Category::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $otherProduct = Product::factory()->create(['restaurant_id' => $otherRestaurant->id, 'category_id' => $otherCategory->id, 'sort_order' => 10]);

        $this->withoutVite()->actingAs($user)->patch(route('products.reorder'), [
            'ids' => [$otherProduct->id, $ownProduct->id],
        ]);

        // Other product unchanged
        $this->assertEquals(10, $otherProduct->fresh()->sort_order);
        // Own product gets index 1
        $this->assertEquals(1, $ownProduct->fresh()->sort_order);
    }

    public function test_product_reorder_requires_ids(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->patch(route('products.reorder'), []);

        $response->assertSessionHasErrors('ids');
    }
}
