<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItemModifier extends Model
{
    /** @use HasFactory<\Database\Factories\PosSaleItemModifierFactory> */
    use HasFactory;

    protected $fillable = [
        'pos_sale_item_id',
        'modifier_option_id',
        'modifier_option_template_id',
        'modifier_option_name',
        'price_adjustment',
        'production_cost',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'production_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }
}
