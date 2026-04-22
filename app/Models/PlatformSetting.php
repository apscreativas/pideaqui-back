<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value store for app-wide settings that are not scoped to a tenant.
 * Read through a process-cache via `Cache::rememberForever` to avoid hitting
 * the database on every request; writes invalidate the cached entry.
 *
 * Typical keys: `public_menu_base_url`.
 */
class PlatformSetting extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'updated_at'];

    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever(self::cacheKey($key), function () use ($key) {
            return self::query()->where('key', $key)->value('value');
        });

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        self::query()->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()],
        );
        Cache::forget(self::cacheKey($key));
    }

    public static function forget(string $key): void
    {
        self::query()->where('key', $key)->delete();
        Cache::forget(self::cacheKey($key));
    }

    private static function cacheKey(string $key): string
    {
        return 'platform_settings:'.$key;
    }
}
