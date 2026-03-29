<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTemplate;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTemplate;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSchedule;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModifierCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── Admin CRUD Tests ────────────────────────────────────────────────────

    public function test_admin_can_view_catalog_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('modifier-catalog.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Modifiers/Index'));
    }

    public function test_admin_can_create_catalog_template(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->post(route('modifier-catalog.store'), [
            'name' => 'Tipo de Tortilla',
            'selection_type' => 'single',
            'is_required' => true,
            'is_active' => true,
            'options' => [
                ['name' => 'Maíz', 'price_adjustment' => 0, 'production_cost' => 0],
                ['name' => 'Harina', 'price_adjustment' => 5, 'production_cost' => 2],
            ],
        ]);

        $response->assertRedirect(route('modifier-catalog.index'));
        $this->assertDatabaseHas('modifier_group_templates', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Tipo de Tortilla',
            'selection_type' => 'single',
            'is_required' => true,
        ]);

        $template = ModifierGroupTemplate::where('name', 'Tipo de Tortilla')->first();
        $this->assertCount(2, $template->options);
    }

    public function test_admin_can_update_catalog_template(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $template = ModifierGroupTemplate::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Old Name']);
        ModifierOptionTemplate::factory()->create(['modifier_group_template_id' => $template->id, 'name' => 'Option A']);

        $response = $this->withoutVite()->actingAs($user)->put(route('modifier-catalog.update', $template), [
            'name' => 'New Name',
            'selection_type' => 'multiple',
            'is_required' => false,
            'max_selections' => 3,
            'is_active' => true,
            'options' => [
                ['name' => 'Option B', 'price_adjustment' => 10, 'production_cost' => 5],
            ],
        ]);

        $response->assertRedirect(route('modifier-catalog.index'));
        $template->refresh();
        $this->assertEquals('New Name', $template->name);
        $this->assertEquals('multiple', $template->selection_type);
        $this->assertEquals(3, $template->max_selections);
        $this->assertCount(1, $template->options);
        $this->assertEquals('Option B', $template->options->first()->name);
    }

    public function test_admin_can_delete_catalog_template(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $template = ModifierGroupTemplate::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->delete(route('modifier-catalog.destroy', $template));

        $response->assertRedirect(route('modifier-catalog.index'));
        $this->assertDatabaseMissing('modifier_group_templates', ['id' => $template->id]);
    }

    public function test_admin_can_toggle_catalog_template(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $template = ModifierGroupTemplate::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $this->withoutVite()->actingAs($user)->patch(route('modifier-catalog.toggle', $template));

        $template->refresh();
        $this->assertFalse($template->is_active);
    }

    public function test_admin_cannot_access_other_restaurant_template(): void
    {
        [$user] = $this->createAdminWithRestaurant();
        $otherRestaurant = Restaurant::factory()->create();
        $template = ModifierGroupTemplate::factory()->create(['restaurant_id' => $otherRestaurant->id]);

        $response = $this->withoutVite()->actingAs($user)->put(route('modifier-catalog.update', $template), [
            'name' => 'Hacked',
            'selection_type' => 'single',
            'options' => [['name' => 'X', 'price_adjustment' => 0]],
        ]);

        $response->assertNotFound(); // TenantScope returns 404 for other tenant's resources.
    }

    // ─── Product ↔ Catalog Template Linking ──────────────────────────────────

    public function test_product_can_link_catalog_templates(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        $template = ModifierGroupTemplate::factory()->create(['restaurant_id' => $restaurant->id]);
        ModifierOptionTemplate::factory()->create(['modifier_group_template_id' => $template->id]);

        $response = $this->withoutVite()->actingAs($user)->post(route('products.store'), [
            'name' => 'Taco',
            'price' => 50,
            'production_cost' => 20,
            'category_id' => $category->id,
            'is_active' => true,
            'modifier_groups' => [],
            'catalog_template_ids' => [$template->id],
        ]);

        $response->assertRedirect(route('menu.index'));
        $product = Product::where('name', 'Taco')->first();
        $this->assertCount(1, $product->modifierGroupTemplates);
        $this->assertEquals($template->id, $product->modifierGroupTemplates->first()->id);
    }

    public function test_product_can_have_both_inline_and_catalog_modifiers(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

        $template = ModifierGroupTemplate::factory()->create(['restaurant_id' => $restaurant->id]);
        ModifierOptionTemplate::factory()->create(['modifier_group_template_id' => $template->id]);

        $response = $this->withoutVite()->actingAs($user)->post(route('products.store'), [
            'name' => 'Burrito',
            'price' => 80,
            'production_cost' => 30,
            'category_id' => $category->id,
            'is_active' => true,
            'modifier_groups' => [
                [
                    'name' => 'Salsa',
                    'selection_type' => 'single',
                    'is_required' => false,
                    'options' => [['name' => 'Verde', 'price_adjustment' => 0, 'production_cost' => 0]],
                ],
            ],
            'catalog_template_ids' => [$template->id],
        ]);

        $response->assertRedirect(route('menu.index'));
        $product = Product::where('name', 'Burrito')->first();
        $this->assertCount(1, $product->modifierGroups);
        $this->assertCount(1, $product->modifierGroupTemplates);
    }

    // ─── API Menu Merge ──────────────────────────────────────────────────────

    public function test_api_menu_merges_both_modifier_sources(): void
    {
        $restaurant = Restaurant::factory()->create(['access_token' => 'merge-test-token', 'is_active' => true]);
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Inline modifier
        $inlineGroup = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'Inline Group',
            'is_active' => true,
        ]);
        ModifierOption::factory()->create(['modifier_group_id' => $inlineGroup->id, 'name' => 'Inline Opt', 'is_active' => true]);

        // Catalog modifier
        $template = ModifierGroupTemplate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Catalog Group',
            'is_active' => true,
        ]);
        ModifierOptionTemplate::factory()->create(['modifier_group_template_id' => $template->id, 'name' => 'Catalog Opt', 'is_active' => true]);
        $product->modifierGroupTemplates()->attach($template->id, ['sort_order' => 0]);

        $response = $this->getJson('/api/menu', ['Authorization' => 'Bearer merge-test-token']);

        $response->assertOk();
        $menuData = $response->json('data');
        $productData = $menuData[0]['products'][0];
        $groups = $productData['modifier_groups'];

        $this->assertCount(2, $groups);

        $sources = collect($groups)->pluck('source')->sort()->values()->all();
        $this->assertEquals(['catalog', 'inline'], $sources);
    }

    public function test_api_menu_excludes_inactive_template_groups(): void
    {
        $restaurant = Restaurant::factory()->create(['access_token' => 'inactive-test', 'is_active' => true]);
        $category = Category::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $template = ModifierGroupTemplate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => false,
        ]);
        ModifierOptionTemplate::factory()->create(['modifier_group_template_id' => $template->id]);
        $product->modifierGroupTemplates()->attach($template->id, ['sort_order' => 0]);

        $response = $this->getJson('/api/menu', ['Authorization' => 'Bearer inactive-test']);

        $response->assertOk();
        $productData = $response->json('data.0.products.0');
        $this->assertCount(0, $productData['modifier_groups']);
    }

    // ─── POST /api/orders with catalog modifiers ─────────────────────────────

    private function setupOrderTest(): array
    {
        $this->mockGoogleMaps();

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'catalog-order-token',
            'is_active' => true,
            'orders_limit' => 100,
            'allows_pickup' => true,
        ]);

        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $branch = Branch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'price' => 50.00,
            'is_active' => true,
        ]);

        $template = ModifierGroupTemplate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'selection_type' => 'single',
            'is_required' => false,
            'is_active' => true,
        ]);

        $option = ModifierOptionTemplate::factory()->create([
            'modifier_group_template_id' => $template->id,
            'price_adjustment' => 10.00,
            'production_cost' => 3.00,
            'is_active' => true,
        ]);

        $product->modifierGroupTemplates()->attach($template->id, ['sort_order' => 0]);

        return [$restaurant, $branch, $product, $template, $option];
    }

    private function mockGoogleMaps(float $distanceKm = 1.5): void
    {
        $mock = $this->createMock(GoogleMapsService::class);
        $mock->method('getDistances')->willReturn([
            ['distance_km' => $distanceKm, 'duration_minutes' => 5],
        ]);
        $this->instance(GoogleMapsService::class, $mock);
    }

    public function test_order_with_catalog_modifier_succeeds(): void
    {
        [$restaurant, $branch, $product, $template, $option] = $this->setupOrderTest();

        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'test-token-1', 'name' => 'Juan', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
                'modifiers' => [
                    ['modifier_option_template_id' => $option->id, 'price_adjustment' => 10.00],
                ],
            ]],
        ], ['Authorization' => 'Bearer catalog-order-token']);

        $response->assertCreated();

        // Verify snapshot was created correctly.
        $this->assertDatabaseHas('order_item_modifiers', [
            'modifier_option_id' => null,
            'modifier_option_name' => $option->name,
            'price_adjustment' => '10.00',
            'production_cost' => '3.00',
        ]);
    }

    public function test_order_with_catalog_modifier_anti_tampering(): void
    {
        [$restaurant, $branch, $product, $template, $option] = $this->setupOrderTest();

        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'test-token-2', 'name' => 'Ana', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
                'modifiers' => [
                    ['modifier_option_template_id' => $option->id, 'price_adjustment' => 0.01],
                ],
            ]],
        ], ['Authorization' => 'Bearer catalog-order-token']);

        $response->assertUnprocessable();
    }

    public function test_order_with_unlinked_catalog_modifier_returns_422(): void
    {
        [$restaurant, $branch, $product] = $this->setupOrderTest();

        // Create a template NOT linked to the product.
        $unlinkedTemplate = ModifierGroupTemplate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);
        $unlinkedOption = ModifierOptionTemplate::factory()->create([
            'modifier_group_template_id' => $unlinkedTemplate->id,
            'price_adjustment' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'test-token-3', 'name' => 'Luis', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
                'modifiers' => [
                    ['modifier_option_template_id' => $unlinkedOption->id, 'price_adjustment' => 5.00],
                ],
            ]],
        ], ['Authorization' => 'Bearer catalog-order-token']);

        $response->assertUnprocessable();
    }

    public function test_order_with_both_inline_and_catalog_modifiers(): void
    {
        [$restaurant, $branch, $product, $template, $catalogOption] = $this->setupOrderTest();

        // Add an inline modifier too.
        $inlineGroup = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        $inlineOption = ModifierOption::factory()->create([
            'modifier_group_id' => $inlineGroup->id,
            'price_adjustment' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'test-token-4', 'name' => 'Pedro', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 50.00,
                'modifiers' => [
                    ['modifier_option_id' => $inlineOption->id, 'price_adjustment' => 5.00],
                    ['modifier_option_template_id' => $catalogOption->id, 'price_adjustment' => 10.00],
                ],
            ]],
        ], ['Authorization' => 'Bearer catalog-order-token']);

        $response->assertCreated();

        // Total: (50 + 5 + 10) * 2 = 130
        $order = \App\Models\Order::latest()->first();
        $this->assertEquals('130.00', $order->subtotal);
        $this->assertCount(2, $order->items->first()->modifiers);
    }

    public function test_order_with_inactive_catalog_option_returns_422(): void
    {
        [$restaurant, $branch, $product, $template] = $this->setupOrderTest();

        $inactiveOption = ModifierOptionTemplate::factory()->create([
            'modifier_group_template_id' => $template->id,
            'price_adjustment' => 5.00,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'test-token-5', 'name' => 'Rosa', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
                'modifiers' => [
                    ['modifier_option_template_id' => $inactiveOption->id, 'price_adjustment' => 5.00],
                ],
            ]],
        ], ['Authorization' => 'Bearer catalog-order-token']);

        $response->assertUnprocessable();
    }

    public function test_required_catalog_group_must_have_selection(): void
    {
        $this->mockGoogleMaps();

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'req-catalog-token',
            'is_active' => true,
            'orders_limit' => 100,
            'allows_pickup' => true,
        ]);

        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price' => 30, 'is_active' => true]);

        $template = ModifierGroupTemplate::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_required' => true,
            'is_active' => true,
        ]);
        ModifierOptionTemplate::factory()->create(['modifier_group_template_id' => $template->id, 'is_active' => true]);
        $product->modifierGroupTemplates()->attach($template->id, ['sort_order' => 0]);

        // Order without selecting from required catalog group.
        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'test-req', 'name' => 'Carlos', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 30.00,
                'modifiers' => [],
            ]],
        ], ['Authorization' => 'Bearer req-catalog-token']);

        $response->assertUnprocessable();
    }

    // ─── Backward compatibility ──────────────────────────────────────────────

    public function test_existing_inline_modifiers_still_work(): void
    {
        $this->mockGoogleMaps();

        $restaurant = Restaurant::factory()->create([
            'access_token' => 'compat-token',
            'is_active' => true,
            'orders_limit' => 100,
            'allows_pickup' => true,
        ]);

        PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        RestaurantSchedule::factory()->create([
            'restaurant_id' => $restaurant->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'is_closed' => false,
        ]);

        $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price' => 40, 'is_active' => true]);

        $group = ModifierGroup::factory()->create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        $option = ModifierOption::factory()->create([
            'modifier_group_id' => $group->id,
            'price_adjustment' => 15.00,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => ['token' => 'compat-cust', 'name' => 'Mario', 'phone' => '5512345678'],
            'delivery_type' => 'pickup',
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 40.00,
                'modifiers' => [
                    ['modifier_option_id' => $option->id, 'price_adjustment' => 15.00],
                ],
            ]],
        ], ['Authorization' => 'Bearer compat-token']);

        $response->assertCreated();
        $this->assertDatabaseHas('order_item_modifiers', [
            'modifier_option_id' => $option->id,
            'price_adjustment' => '15.00',
        ]);
    }
}
