<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500, 'max_branches' => 3]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── View ────────────────────────────────────────────────────────────────────

    public function test_admin_can_view_branding_page(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.branding'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/Branding'));
    }

    public function test_branding_page_returns_current_values(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $restaurant->update(['primary_color' => '#000000', 'secondary_color' => '#FF0000']);

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.branding'));

        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Branding')
            ->where('restaurant.primary_color', '#000000')
            ->where('restaurant.secondary_color', '#FF0000')
        );
    }

    // ─── Update Colors ──────────────────────────────────────────────────────────

    public function test_admin_can_save_valid_hex_colors(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'primary_color' => '#1a1a1a',
            'secondary_color' => '#00BCD4',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $restaurant->refresh();
        $this->assertEquals('#1a1a1a', $restaurant->primary_color);
        $this->assertEquals('#00BCD4', $restaurant->secondary_color);
    }

    public function test_admin_can_save_short_hex_colors(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'primary_color' => '#fff',
            'secondary_color' => '#f00',
        ]);

        $response->assertRedirect();
        $restaurant->refresh();
        $this->assertEquals('#fff', $restaurant->primary_color);
        $this->assertEquals('#f00', $restaurant->secondary_color);
    }

    public function test_admin_can_clear_colors_to_null(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $restaurant->update(['primary_color' => '#000000', 'secondary_color' => '#FF0000']);

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'primary_color' => null,
            'secondary_color' => null,
        ]);

        $response->assertRedirect();
        $restaurant->refresh();
        $this->assertNull($restaurant->primary_color);
        $this->assertNull($restaurant->secondary_color);
    }

    public function test_invalid_hex_color_is_rejected(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'primary_color' => 'not-a-color',
        ]);

        $response->assertSessionHasErrors('primary_color');
    }

    public function test_invalid_hex_without_hash_is_rejected(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'secondary_color' => 'FF5722',
        ]);

        $response->assertSessionHasErrors('secondary_color');
    }

    // ─── Default Product Image ──────────────────────────────────────────────────

    public function test_admin_can_upload_default_product_image(): void
    {
        Storage::fake('public');
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'default_product_image' => UploadedFile::fake()->image('default.jpg'),
        ]);

        $response->assertRedirect();
        $restaurant->refresh();
        $this->assertNotNull($restaurant->default_product_image);
        Storage::disk('public')->assertExists($restaurant->default_product_image);
    }

    public function test_admin_can_remove_default_product_image(): void
    {
        Storage::fake('public');
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        // First upload an image
        $path = UploadedFile::fake()->image('default.jpg')->store("restaurants/{$restaurant->id}", 'public');
        $restaurant->update(['default_product_image' => $path]);

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'remove_default_image' => true,
        ]);

        $response->assertRedirect();
        $restaurant->refresh();
        $this->assertNull($restaurant->default_product_image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_svg_upload_is_rejected(): void
    {
        Storage::fake('public');
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'default_product_image' => UploadedFile::fake()->create('image.svg', 100, 'image/svg+xml'),
        ]);

        $response->assertSessionHasErrors('default_product_image');
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('public');
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'default_product_image' => UploadedFile::fake()->image('huge.jpg')->size(3000),
        ]);

        $response->assertSessionHasErrors('default_product_image');
    }

    public function test_uploading_new_image_deletes_old_one(): void
    {
        Storage::fake('public');
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        // Upload first image
        $this->actingAs($user)->put(route('settings.branding.update'), [
            'default_product_image' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $restaurant->refresh();
        $oldPath = $restaurant->default_product_image;

        // Upload second image
        $this->actingAs($user)->put(route('settings.branding.update'), [
            'default_product_image' => UploadedFile::fake()->image('second.jpg'),
        ]);
        $restaurant->refresh();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($restaurant->default_product_image);
        $this->assertNotEquals($oldPath, $restaurant->default_product_image);
    }

    // ─── API Response ───────────────────────────────────────────────────────────

    public function test_api_restaurant_includes_branding_fields(): void
    {
        $restaurant = Restaurant::factory()->create([
            'primary_color' => '#222222',
            'secondary_color' => '#00FF00',
            'is_active' => true,
        ]);
        $restaurant->paymentMethods()->create([
            'type' => 'cash',
            'label' => 'Efectivo',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ])->getJson('/api/restaurant');

        $response->assertOk();
        $response->assertJsonFragment([
            'primary_color' => '#222222',
            'secondary_color' => '#00FF00',
        ]);
        $response->assertJsonPath('data.default_product_image_url', null);
    }

    public function test_api_restaurant_returns_null_for_unset_branding(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $restaurant->paymentMethods()->create([
            'type' => 'cash',
            'label' => 'Efectivo',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ])->getJson('/api/restaurant');

        $response->assertOk();
        $response->assertJsonPath('data.primary_color', null);
        $response->assertJsonPath('data.secondary_color', null);
        $response->assertJsonPath('data.default_product_image_url', null);
    }

    // ─── Text Color ─────────────────────────────────────────────────────────────

    public function test_admin_can_save_text_color(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'text_color' => 'light',
        ]);

        $response->assertRedirect();
        $restaurant->refresh();
        $this->assertEquals('light', $restaurant->text_color);
    }

    public function test_admin_can_clear_text_color_to_auto(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $restaurant->update(['text_color' => 'light']);

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'text_color' => null,
        ]);

        $response->assertRedirect();
        $restaurant->refresh();
        $this->assertNull($restaurant->text_color);
    }

    public function test_invalid_text_color_is_rejected(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.branding.update'), [
            'text_color' => 'blue',
        ]);

        $response->assertSessionHasErrors('text_color');
    }

    public function test_api_resolves_text_color_from_dark_background(): void
    {
        $restaurant = Restaurant::factory()->create([
            'primary_color' => '#000000',
            'text_color' => null,
            'is_active' => true,
        ]);
        $restaurant->paymentMethods()->create([
            'type' => 'cash',
            'label' => 'Efectivo',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ])->getJson('/api/restaurant');

        $response->assertOk();
        $response->assertJsonPath('data.text_color', 'light');
    }

    public function test_api_resolves_text_color_from_light_background(): void
    {
        $restaurant = Restaurant::factory()->create([
            'primary_color' => '#FFFFFF',
            'text_color' => null,
            'is_active' => true,
        ]);
        $restaurant->paymentMethods()->create([
            'type' => 'cash',
            'label' => 'Efectivo',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ])->getJson('/api/restaurant');

        $response->assertOk();
        $response->assertJsonPath('data.text_color', 'dark');
    }

    public function test_api_returns_explicit_text_color_when_set(): void
    {
        $restaurant = Restaurant::factory()->create([
            'primary_color' => '#000000',
            'text_color' => 'dark',
            'is_active' => true,
        ]);
        $restaurant->paymentMethods()->create([
            'type' => 'cash',
            'label' => 'Efectivo',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ])->getJson('/api/restaurant');

        $response->assertOk();
        // Explicit value overrides auto-detection
        $response->assertJsonPath('data.text_color', 'dark');
    }

    public function test_api_defaults_to_dark_when_no_primary_color(): void
    {
        $restaurant = Restaurant::factory()->create([
            'primary_color' => null,
            'text_color' => null,
            'is_active' => true,
        ]);
        $restaurant->paymentMethods()->create([
            'type' => 'cash',
            'label' => 'Efectivo',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$restaurant->access_token,
        ])->getJson('/api/restaurant');

        $response->assertOk();
        $response->assertJsonPath('data.text_color', 'dark');
    }

    // ─── Auth guard ─────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_branding(): void
    {
        $response = $this->get(route('settings.branding'));

        $response->assertRedirect(route('login'));
    }
}
