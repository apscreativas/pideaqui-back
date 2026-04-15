<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'restaurant_id',
        'code',
        'discount_type',
        'discount_value',
        'max_discount',
        'min_purchase',
        'starts_at',
        'ends_at',
        'max_uses_per_customer',
        'max_total_uses',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses_per_customer' => 'integer',
            'max_total_uses' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function uses(): HasMany
    {
        return $this->hasMany(CouponUse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Validate the coupon for a given order context.
     *
     * When `$lockUses` is true, the count queries on `coupon_uses` are wrapped
     * with `lockForUpdate()`. This is required when re-validating inside the
     * order-creation transaction to prevent two concurrent orders from both
     * passing `max_total_uses` / `max_uses_per_customer` checks. The caller
     * MUST also `lockForUpdate()` the parent Coupon row before calling this,
     * so concurrent callers serialize on that row lock.
     *
     * The public `/api/coupons/validate` endpoint calls this without locks;
     * that pre-check is non-authoritative — the authoritative check happens
     * inside `OrderService::store` with `$lockUses = true`.
     *
     * @return array{valid: bool, reason: ?string}
     */
    public function isValidForOrder(float $subtotal, string $customerPhone, bool $lockUses = false): array
    {
        if (! $this->is_active) {
            return ['valid' => false, 'reason' => 'Este cupón no está activo.'];
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return ['valid' => false, 'reason' => 'Este cupón aún no está vigente.'];
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return ['valid' => false, 'reason' => 'Este cupón ha expirado.'];
        }

        if ($subtotal < (float) $this->min_purchase) {
            return ['valid' => false, 'reason' => 'El pedido mínimo para este cupón es de $'.number_format((float) $this->min_purchase, 2).'.'];
        }

        if ($this->max_uses_per_customer !== null) {
            $customerUsesQuery = $this->uses()->where('customer_phone', $customerPhone);
            if ($lockUses) {
                $customerUsesQuery->lockForUpdate();
            }
            $customerUses = $customerUsesQuery->count();
            if ($customerUses >= $this->max_uses_per_customer) {
                return ['valid' => false, 'reason' => 'Ya usaste este cupón el máximo de veces permitido.'];
            }
        }

        if ($this->max_total_uses !== null) {
            $totalUsesQuery = $this->uses();
            if ($lockUses) {
                $totalUsesQuery->lockForUpdate();
            }
            $totalUses = $totalUsesQuery->count();
            if ($totalUses >= $this->max_total_uses) {
                return ['valid' => false, 'reason' => 'Este cupón ha alcanzado su límite de usos.'];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'fixed') {
            $discount = (float) $this->discount_value;
        } else {
            $discount = $subtotal * ((float) $this->discount_value / 100);

            if ($this->max_discount !== null) {
                $discount = min($discount, (float) $this->max_discount);
            }
        }

        return round(min($discount, $subtotal), 2);
    }
}
