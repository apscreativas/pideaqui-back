<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RestaurantSpecialDate extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantSpecialDateFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'restaurant_id',
        'date',
        'type',
        'opens_at',
        'closes_at',
        'label',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Find a special date entry for a given date (exact match or recurring month+day).
     */
    public static function findForDate(int $restaurantId, Carbon $date): ?self
    {
        // Exact date match first.
        $exact = static::query()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($exact) {
            return $exact;
        }

        // Recurring match: same month + day, any year.
        return static::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_recurring', true)
            ->whereMonth('date', $date->month)
            ->whereDay('date', $date->day)
            ->first();
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereDate('date', '>=', now()->toDateString())
                ->orWhere('is_recurring', true);
        })->orderBy('date');
    }

    public function scopeHolidays(Builder $query): Builder
    {
        return $query->where('type', 'closed');
    }

    public function scopeSpecialHours(Builder $query): Builder
    {
        return $query->where('type', 'special');
    }
}
