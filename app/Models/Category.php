<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'description',
        'image_path',
        'sort_order',
        'is_active',
        'available_days',
        'available_from',
        'available_until',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'available_days' => 'array',
        ];
    }

    protected $appends = ['image_url'];

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($this->image_path)
                : null,
        );
    }

    protected function availableFrom(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : null,
        );
    }

    protected function availableUntil(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : null,
        );
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort_order');
    }

    public function isCurrentlyAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // No schedule restriction — always available.
        if ($this->available_days === null) {
            return true;
        }

        $now = Carbon::now(config('app.timezone'));

        if (! in_array($now->dayOfWeek, $this->available_days)) {
            return false;
        }

        if (! $this->available_from || ! $this->available_until) {
            return true;
        }

        $currentTime = $now->format('H:i');

        // Overnight support (e.g. 20:00–02:00).
        if ($this->available_from > $this->available_until) {
            return $currentTime >= $this->available_from || $currentTime <= $this->available_until;
        }

        return $currentTime >= $this->available_from && $currentTime <= $this->available_until;
    }
}
