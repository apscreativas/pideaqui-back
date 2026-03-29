<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOptionTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\ModifierOptionTemplateFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'modifier_group_template_id',
        'name',
        'price_adjustment',
        'production_cost',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'production_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function modifierGroupTemplate(): BelongsTo
    {
        return $this->belongsTo(ModifierGroupTemplate::class);
    }
}
