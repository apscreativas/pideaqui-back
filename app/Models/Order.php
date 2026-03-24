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
        'coupon_id',
        'coupon_code',
        'delivery_type',
        'status',
        'scheduled_at',
        'subtotal',
        'delivery_cost',
        'discount_amount',
        'total',
        'payment_method',
        'cash_amount',
        'requires_invoice',
        'distance_km',
        'address_street',
        'address_number',
        'address_colony',
        'address_references',
        'latitude',
        'longitude',
        'cancellation_reason',
        'cancelled_at',
        'edited_at',
        'edit_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'requires_invoice' => 'boolean',
            'distance_km' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'cancelled_at' => 'datetime',
            'edited_at' => 'datetime',
            'edit_count' => 'integer',
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

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['received', 'preparing']);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['received', 'preparing']);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(OrderAudit::class)->orderBy('created_at');
    }
}
