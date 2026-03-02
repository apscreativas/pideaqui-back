<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'restaurant_id',
        'branch_id',
        'customer_id',
        'delivery_type',
        'status',
        'scheduled_at',
        'subtotal',
        'delivery_cost',
        'total',
        'payment_method',
        'distance_km',
        'address',
        'address_references',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
