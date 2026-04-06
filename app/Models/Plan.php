<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'orders_limit',
        'max_branches',
        'monthly_price',
        'yearly_price',
        'stripe_product_id',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'is_default_grace',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'orders_limit' => 'integer',
            'max_branches' => 'integer',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'is_default_grace' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    public static function gracePlan(): ?self
    {
        return static::query()->where('is_default_grace', true)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function purchasable(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_default_grace', false)
            ->orderBy('sort_order')
            ->get();
    }
}
