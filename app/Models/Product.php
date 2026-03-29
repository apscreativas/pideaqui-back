<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'description',
        'price',
        'production_cost',
        'image_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'production_cost' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
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

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('sort_order');
    }

    public function modifierGroupTemplates(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroupTemplate::class, 'product_modifier_group_template')
            ->withPivot('sort_order')
            ->orderBy('product_modifier_group_template.sort_order');
    }

    /**
     * Merge per-product (inline) and catalog (template) modifier groups into a unified collection.
     * Each item includes a 'source' key ('inline' or 'catalog') for identification.
     */
    public function getAllModifierGroups(): Collection
    {
        $inline = $this->modifierGroups
            ->filter(fn (ModifierGroup $g) => $g->is_active)
            ->map(function (ModifierGroup $g) {
                return [
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
                ];
            });

        $catalog = $this->modifierGroupTemplates
            ->filter(fn (ModifierGroupTemplate $g) => $g->is_active)
            ->map(function (ModifierGroupTemplate $g) {
                return [
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
                ];
            });

        return collect($inline->all())->merge($catalog->all())->values();
    }
}
