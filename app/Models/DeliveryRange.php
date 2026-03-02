<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRange extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryRangeFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'restaurant_id',
        'min_km',
        'max_km',
        'price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_km' => 'decimal:2',
            'max_km' => 'decimal:2',
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
