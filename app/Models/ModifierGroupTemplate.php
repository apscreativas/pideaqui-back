<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroupTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\ModifierGroupTemplateFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'restaurant_id',
        'name',
        'selection_type',
        'is_required',
        'max_selections',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'max_selections' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOptionTemplate::class)->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier_group_template')
            ->withPivot('sort_order');
    }
}
