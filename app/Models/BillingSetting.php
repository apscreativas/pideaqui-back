<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, string $default = ''): string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, (string) $default);
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
