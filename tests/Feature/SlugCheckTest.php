<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_slug_returns_true(): void
    {
        $this->getJson('/api/slug-check?slug=el-puebla')
            ->assertOk()
            ->assertJson(['available' => true, 'slug' => 'el-puebla']);
    }

    public function test_taken_slug_returns_false_with_suggestions(): void
    {
        Restaurant::factory()->create(['slug' => 'tacos-el-rey']);

        $this->getJson('/api/slug-check?slug=tacos-el-rey')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'taken')
            ->assertJsonFragment(['suggestions' => ['tacos-el-rey-2', 'tacos-el-rey-3', 'tacos-el-rey-4']]);
    }

    public function test_reserved_slug_returns_reserved_reason(): void
    {
        $this->getJson('/api/slug-check?slug=admin')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'reserved');
    }

    public function test_invalid_format_is_rejected(): void
    {
        $this->getJson('/api/slug-check?slug=FOO_BAR')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'invalid_format');
    }

    public function test_consecutive_hyphens_rejected(): void
    {
        $this->getJson('/api/slug-check?slug=foo--bar')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'invalid_format');
    }

    public function test_too_short_rejected(): void
    {
        $this->getJson('/api/slug-check?slug=ab')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'invalid_format');
    }

    public function test_empty_slug_returns_invalid_format(): void
    {
        $this->getJson('/api/slug-check?slug=')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'invalid_format');
    }

    public function test_endpoint_lowercases_input_before_checking(): void
    {
        // The endpoint normalizes to lowercase so users typing in caps get
        // the same answer as the canonical slug form. The Vue SlugInput
        // mirrors this client-side.
        $this->getJson('/api/slug-check?slug=EL-PUEBLA')
            ->assertOk()
            ->assertJson(['available' => true, 'slug' => 'el-puebla']);
    }

    public function test_endpoint_is_throttled(): void
    {
        // Limit is 120/min; loop past the ceiling so the last request is rejected.
        for ($i = 0; $i < 121; $i++) {
            $response = $this->getJson('/api/slug-check?slug=sample-'.$i);
        }
        $response->assertStatus(429);
    }
}
