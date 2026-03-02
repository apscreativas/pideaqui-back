<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOption extends Model
{
    /** @use HasFactory<\Database\Factories\ModifierOptionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'modifier_group_id',
        'name',
        'price_adjustment',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class);
    }
}
