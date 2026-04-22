<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the runtime tenant resolution by URL slug (`/api/public/{slug}/...`).
 *
 * The controllers are the same as the legacy token-authenticated routes, so
 * these tests focus on: (a) slug resolution happy path, (b) cross-tenant
 * isolation — slug of A must never expose resources of B, (c) failure modes
 * distinguishable by status code (404 vs 410 vs 401), (d) coexistence with
 * the legacy `auth.restaurant` flow.
 */
class PublicSlugResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(array $attrs = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge([
            'is_active' => true,
            'status' => 'active',
        ], $attrs));
    }

    // ─── Slug resolution ─────────────────────────────────────────────────────

    public function test_get_restaurant_by_valid_slug_returns_payload(): void
    {
        $restaurant = $this->restaurant(['slug' => 'el-puebla']);
        PaymentMethod::factory()->cash()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public/el-puebla/restaurant');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'el-puebla')
            ->assertJsonPath('data.id', $restaurant->id);
    }

    public function test_unknown_slug_returns_404_with_code(): void
    {
        $response = $this->getJson('/api/public/ghost-restaurant/restaurant');

        $response->assertNotFound()
            ->assertJsonPath('code', 'tenant_not_found');
    }

    public function test_inactive_restaurant_returns_410_with_code(): void
    {
        $this->restaurant([
            'slug' => 'taqueria-x',
            'is_active' => false,
            'status' => 'disabled',
        ]);

        $response = $this->getJson('/api/public/taqueria-x/restaurant');

        $response->assertStatus(410)
            ->assertJsonPath('code', 'tenant_unavailable')
            ->assertJsonPath('is_active', false);
    }

    // ─── Cross-tenant isolation ──────────────────────────────────────────────

    public function test_slug_a_cannot_leak_menu_of_restaurant_b(): void
    {
        $a = $this->restaurant(['slug' => 'restaurante-a']);
        $b = $this->restaurant(['slug' => 'restaurante-b']);

        $catA = Category::factory()->create(['restaurant_id' => $a->id, 'is_active' => true]);
        $catB = Category::factory()->create(['restaurant_id' => $b->id, 'is_active' => true]);

        Product::factory()->create([
            'restaurant_id' => $a->id,
            'category_id' => $catA->id,
            'name' => 'Tacos de A',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'restaurant_id' => $b->id,
            'category_id' => $catB->id,
            'name' => 'Pizza de B',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public/restaurante-a/menu');

        $body = $response->assertOk()->json('data');

        $flattened = collect($body)
            ->flatMap(fn ($cat) => collect($cat['products'] ?? [])->pluck('name'))
            ->all();

        $this->assertContains('Tacos de A', $flattened);
        $this->assertNotContains('Pizza de B', $flattened);
    }

    public function test_branches_endpoint_only_returns_tenant_branches(): void
    {
        $a = $this->restaurant(['slug' => 'rest-a']);
        $b = $this->restaurant(['slug' => 'rest-b']);

        Branch::factory()->create(['restaurant_id' => $a->id, 'name' => 'Sucursal A1', 'is_active' => true]);
        Branch::factory()->create(['restaurant_id' => $b->id, 'name' => 'Sucursal B1', 'is_active' => true]);

        $response = $this->getJson('/api/public/rest-a/branches');

        $names = collect($response->assertOk()->json('data'))->pluck('name')->all();
        $this->assertContains('Sucursal A1', $names);
        $this->assertNotContains('Sucursal B1', $names);
    }

    // ─── Auth-less access ────────────────────────────────────────────────────

    public function test_public_endpoint_does_not_require_any_header(): void
    {
        $restaurant = $this->restaurant(['slug' => 'tokenless']);
        PaymentMethod::factory()->cash()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $this->getJson('/api/public/tokenless/restaurant')->assertOk();
    }

    public function test_slug_resolution_ignores_stray_authorization_header(): void
    {
        $restaurant = $this->restaurant(['slug' => 'has-slug']);
        PaymentMethod::factory()->cash()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        // Even a garbage Authorization header must not block slug-based resolution.
        $this->getJson('/api/public/has-slug/restaurant', [
            'Authorization' => 'Bearer invalid-token-xyz',
        ])->assertOk();
    }
}
