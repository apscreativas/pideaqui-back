<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlatformSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_default_when_key_missing(): void
    {
        $this->assertSame('fallback', PlatformSetting::get('never_set', 'fallback'));
        $this->assertNull(PlatformSetting::get('also_missing'));
    }

    public function test_set_persists_and_caches(): void
    {
        PlatformSetting::set('public_menu_base_url', 'https://menu.pideaqui.mx');

        $this->assertSame('https://menu.pideaqui.mx', PlatformSetting::get('public_menu_base_url'));
        $this->assertDatabaseHas('platform_settings', ['key' => 'public_menu_base_url']);
    }

    public function test_set_invalidates_cache_on_rewrite(): void
    {
        PlatformSetting::set('public_menu_base_url', 'https://a.test');
        PlatformSetting::get('public_menu_base_url');
        PlatformSetting::set('public_menu_base_url', 'https://b.test');

        $this->assertSame('https://b.test', PlatformSetting::get('public_menu_base_url'));
    }

    public function test_restaurant_menu_public_url_uses_platform_setting(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'el-puebla']);

        PlatformSetting::set('public_menu_base_url', 'https://menu.pideaqui.mx');

        $this->assertSame('https://menu.pideaqui.mx/r/el-puebla', $restaurant->menuPublicUrl());
    }

    public function test_restaurant_menu_public_url_falls_back_to_app_url(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'fallback-test']);

        // Ensure no platform setting is defined
        Cache::forget('platform_settings:public_menu_base_url');
        PlatformSetting::query()->where('key', 'public_menu_base_url')->delete();

        config()->set('app.url', 'http://localhost');

        $this->assertSame('http://localhost/r/fallback-test', $restaurant->menuPublicUrl());
    }

    public function test_trailing_slashes_are_stripped(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'trail']);

        PlatformSetting::set('public_menu_base_url', 'https://menu.pideaqui.mx/');

        $this->assertSame('https://menu.pideaqui.mx/r/trail', $restaurant->menuPublicUrl());
    }
}
