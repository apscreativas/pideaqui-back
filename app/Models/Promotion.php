<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Promotion extends Model
{
    /** @use HasFactory<\Database\Factories\PromotionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'description',
        'price',
        'production_cost',
        'image_path',
        'is_active',
        'active_days',
        'starts_at',
        'ends_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'production_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'active_days' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @var list<string> */
    protected $appends = ['image_url'];

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($this->image_path)
                : null,
        );
    }

    protected function startsAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : null,
        );
    }

    protected function endsAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : null,
        );
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('sort_order');
    }

    public function modifierGroupTemplates(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroupTemplate::class, 'promotion_modifier_group_template')
            ->withPivot('sort_order')
            ->orderBy('promotion_modifier_group_template.sort_order');
    }

    /**
     * Merge per-promotion (inline) and catalog (template) modifier groups into a unified collection.
     */
    public function getAllModifierGroups(): Collection
    {
        $inline = $this->modifierGroups
            ->filter(fn (ModifierGroup $g) => $g->is_active)
            ->map(fn (ModifierGroup $g) => [
                'id' => $g->id,
                'source' => 'inline',
                'name' => $g->name,
                'selection_type' => $g->selection_type,
                'is_required' => $g->is_required,
                'max_selections' => $g->max_selections,
                'options' => $g->options
                    ->filter(fn (ModifierOption $o) => $o->is_active)
                    ->values()
                    ->map(fn (ModifierOption $o) => [
                        'id' => $o->id,
                        'source' => 'inline',
                        'name' => $o->name,
                        'price_adjustment' => (float) $o->price_adjustment,
                    ]),
            ]);

        $catalog = $this->modifierGroupTemplates
            ->filter(fn (ModifierGroupTemplate $g) => $g->is_active)
            ->map(fn (ModifierGroupTemplate $g) => [
                'id' => $g->id,
                'source' => 'catalog',
                'name' => $g->name,
                'selection_type' => $g->selection_type,
                'is_required' => $g->is_required,
                'max_selections' => $g->max_selections,
                'options' => $g->options
                    ->filter(fn (ModifierOptionTemplate $o) => $o->is_active)
                    ->values()
                    ->map(fn (ModifierOptionTemplate $o) => [
                        'id' => $o->id,
                        'source' => 'catalog',
                        'name' => $o->name,
                        'price_adjustment' => (float) $o->price_adjustment,
                    ]),
            ]);

        return collect($inline->all())->merge($catalog->all())->values();
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now(config('app.timezone'));

        if (! in_array($now->dayOfWeek, $this->active_days ?? [])) {
            return false;
        }

        if (! $this->starts_at || ! $this->ends_at) {
            return true;
        }

        $currentTime = $now->format('H:i');

        if ($this->starts_at > $this->ends_at) {
            return $currentTime >= $this->starts_at || $currentTime <= $this->ends_at;
        }

        return $currentTime >= $this->starts_at && $currentTime <= $this->ends_at;
    }
}
