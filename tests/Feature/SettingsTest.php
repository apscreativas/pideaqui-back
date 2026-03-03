<?php

namespace Tests\Feature;

use App\Models\DeliveryRange;
use App\Models\PaymentMethod;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRestaurant(): array
    {
        $restaurant = Restaurant::factory()->create(['orders_limit' => 500, 'max_branches' => 3]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        return [$user, $restaurant];
    }

    // ─── General ───────────────────────────────────────────────────────────────

    public function test_admin_can_view_general_settings(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.general'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/General'));
    }

    public function test_admin_can_update_restaurant_name(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.general.update'), [
            'name' => 'El Nuevo Nombre',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'name' => 'El Nuevo Nombre']);
    }

    public function test_admin_can_upload_logo(): void
    {
        Storage::fake('public');
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $this->actingAs($user)->put(route('settings.general.update'), [
            'name' => 'Restaurante',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $restaurant->refresh();
        $this->assertNotNull($restaurant->logo_path);
        Storage::disk('public')->assertExists($restaurant->logo_path);
    }

    public function test_general_settings_requires_name(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.general.update'), ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    // ─── Delivery Methods ──────────────────────────────────────────────────────

    public function test_admin_can_view_delivery_methods(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.delivery-methods'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/DeliveryMethods'));
    }

    public function test_admin_can_update_delivery_methods(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        // Delivery requires at least one delivery range
        DeliveryRange::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)->put(route('settings.delivery-methods.update'), [
            'allows_delivery' => true,
            'allows_pickup' => false,
            'allows_dine_in' => false,
        ]);

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'allows_delivery' => true,
            'allows_pickup' => false,
            'allows_dine_in' => false,
        ]);
    }

    public function test_at_least_one_delivery_method_must_be_active(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.delivery-methods.update'), [
            'allows_delivery' => false,
            'allows_pickup' => false,
            'allows_dine_in' => false,
        ]);

        $response->assertSessionHasErrors('allows_delivery');
    }

    public function test_cannot_activate_delivery_without_delivery_ranges(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.delivery-methods.update'), [
            'allows_delivery' => true,
            'allows_pickup' => false,
            'allows_dine_in' => false,
        ]);

        $response->assertSessionHasErrors('allows_delivery');
    }

    // ─── Delivery Ranges ───────────────────────────────────────────────────────

    public function test_admin_can_view_shipping_rates(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.shipping-rates'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/ShippingRates'));
    }

    public function test_admin_can_add_delivery_range(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->post(route('settings.shipping-rates.store'), [
            'min_km' => 0,
            'max_km' => 5,
            'price' => 30,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('delivery_ranges', [
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 5,
            'price' => 30,
        ]);
    }

    public function test_admin_can_delete_delivery_range(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $range = DeliveryRange::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($user)->delete(route('settings.shipping-rates.destroy', $range));

        $response->assertRedirect();
        $this->assertDatabaseMissing('delivery_ranges', ['id' => $range->id]);
    }

    public function test_admin_cannot_delete_range_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();
        $other = Restaurant::factory()->create();
        $otherRange = DeliveryRange::factory()->create(['restaurant_id' => $other->id]);

        $response = $this->actingAs($user)->delete(route('settings.shipping-rates.destroy', $otherRange));

        // TenantScope hides other-tenant resources — returns 404 (correct security behavior)
        $response->assertStatus(404);
        $this->assertDatabaseHas('delivery_ranges', ['id' => $otherRange->id]);
    }

    public function test_delivery_range_max_km_must_be_greater_than_min_km(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->post(route('settings.shipping-rates.store'), [
            'min_km' => 10,
            'max_km' => 5,
            'price' => 30,
        ]);

        $response->assertSessionHasErrors('max_km');
    }

    public function test_overlapping_delivery_range_is_rejected(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        // Create existing range: 0-5 km
        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 5,
        ]);

        // Try to create 0-1 km (fully inside existing)
        $response = $this->actingAs($user)->post(route('settings.shipping-rates.store'), [
            'min_km' => 0,
            'max_km' => 1,
            'price' => 20,
        ]);

        $response->assertSessionHasErrors('min_km');
    }

    public function test_partially_overlapping_delivery_range_is_rejected(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 5,
        ]);

        // Try to create 3-8 km (partial overlap)
        $response = $this->actingAs($user)->post(route('settings.shipping-rates.store'), [
            'min_km' => 3,
            'max_km' => 8,
            'price' => 50,
        ]);

        $response->assertSessionHasErrors('min_km');
    }

    public function test_non_overlapping_delivery_range_is_allowed(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 5,
        ]);

        // 5-10 km: no overlap (starts where the other ends)
        $response = $this->actingAs($user)->post(route('settings.shipping-rates.store'), [
            'min_km' => 5,
            'max_km' => 10,
            'price' => 50,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_update_delivery_range_does_not_conflict_with_itself(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();

        $range = DeliveryRange::factory()->create([
            'restaurant_id' => $restaurant->id,
            'min_km' => 0,
            'max_km' => 5,
        ]);

        // Updating the same range to 0-6 should work (no self-conflict)
        $response = $this->actingAs($user)->put(route('settings.shipping-rates.update', $range), [
            'min_km' => 0,
            'max_km' => 6,
            'price' => 35,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    // ─── Payment Methods ───────────────────────────────────────────────────────

    public function test_admin_can_view_payment_methods(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.payment-methods'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/PaymentMethods'));
    }

    public function test_admin_can_toggle_cash_payment_method(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $pm = PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id]);
        // Need at least one other active method so we can deactivate this one
        PaymentMethod::factory()->terminal()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $this->actingAs($user)->put(route('settings.payment-methods.update', $pm), [
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('payment_methods', ['id' => $pm->id, 'is_active' => false]);
    }

    public function test_cannot_deactivate_last_active_payment_method(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $pm = PaymentMethod::factory()->cash()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);

        $response = $this->actingAs($user)->put(route('settings.payment-methods.update', $pm), [
            'is_active' => false,
        ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertDatabaseHas('payment_methods', ['id' => $pm->id, 'is_active' => true]);
    }

    public function test_cannot_activate_transfer_without_bank_data(): void
    {
        [$user, $restaurant] = $this->createAdminWithRestaurant();
        $pm = PaymentMethod::factory()->transfer()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($user)->put(route('settings.payment-methods.update', $pm), [
            'is_active' => true,
            'bank_name' => '',
            'account_holder' => '',
            'clabe' => '',
        ]);

        $response->assertSessionHasErrors(['bank_name', 'account_holder', 'clabe']);
    }

    public function test_admin_cannot_update_payment_method_from_another_restaurant(): void
    {
        [$user] = $this->createAdminWithRestaurant();
        $other = Restaurant::factory()->create();
        $pm = PaymentMethod::factory()->cash()->create(['restaurant_id' => $other->id]);

        $response = $this->actingAs($user)->put(route('settings.payment-methods.update', $pm), [
            'is_active' => false,
        ]);

        // TenantScope hides other-tenant resources — returns 404 (correct security behavior)
        $response->assertStatus(404);
    }

    // ─── Profile ───────────────────────────────────────────────────────────────

    public function test_admin_can_view_profile(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.profile'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/Profile'));
    }

    public function test_admin_can_update_profile_name_and_email(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'name' => 'Nuevo Nombre',
            'email' => 'nuevo@example.com',
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nuevo Nombre', 'email' => 'nuevo@example.com']);
    }

    public function test_cannot_change_password_with_wrong_current_password(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->actingAs($user)->put(route('settings.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    // ─── Limits ────────────────────────────────────────────────────────────────

    public function test_admin_can_view_limits(): void
    {
        [$user] = $this->createAdminWithRestaurant();

        $response = $this->withoutVite()->actingAs($user)->get(route('settings.limits'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Limits')
            ->has('orders_count')
            ->has('orders_limit')
            ->has('branch_count')
            ->has('max_branches')
        );
    }

    // ─── Auth ──────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_redirected_from_settings(): void
    {
        $response = $this->get(route('settings.general'));

        $response->assertRedirect(route('login'));
    }
}
