<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Restaurant extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'access_token',
        'is_active',
        'allows_delivery',
        'allows_pickup',
        'allows_dine_in',
        'orders_limit',
        'orders_limit_start',
        'orders_limit_end',
        'notify_new_orders',
        'max_branches',
        'instagram',
        'facebook',
        'tiktok',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allows_delivery' => 'boolean',
            'allows_pickup' => 'boolean',
            'allows_dine_in' => 'boolean',
            'orders_limit' => 'integer',
            'orders_limit_start' => 'date',
            'orders_limit_end' => 'date',
            'notify_new_orders' => 'boolean',
            'max_branches' => 'integer',
        ];
    }

    protected $appends = ['logo_url'];

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo_path
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($this->logo_path)
                : null,
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function deliveryRanges(): HasMany
    {
        return $this->hasMany(DeliveryRange::class)->orderBy('sort_order');
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class)->orderBy('sort_order');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(RestaurantSchedule::class)->orderBy('day_of_week');
    }

    /**
     * @return array<int, string>
     */
    public function routeNotificationForMail(): array
    {
        return $this->users->pluck('email')->all();
    }

    public function isCurrentlyOpen(): bool
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');

        // Check today's schedule.
        $todaySchedule = $this->schedules->firstWhere('day_of_week', $now->dayOfWeek);

        if ($todaySchedule && ! $todaySchedule->is_closed && $todaySchedule->opens_at && $todaySchedule->closes_at) {
            if ($todaySchedule->opens_at > $todaySchedule->closes_at) {
                // Overnight: 22:00–02:00. If current time >= opens_at, we're in the first part.
                if ($currentTime >= $todaySchedule->opens_at) {
                    return true;
                }
            } elseif ($currentTime >= $todaySchedule->opens_at && $currentTime <= $todaySchedule->closes_at) {
                return true;
            }
        }

        // Check yesterday's overnight carryover.
        // E.g., 01:00 Tuesday — check if Monday had 22:00–02:00.
        $yesterdayDow = ($now->dayOfWeek + 6) % 7;
        $yesterdaySchedule = $this->schedules->firstWhere('day_of_week', $yesterdayDow);

        if ($yesterdaySchedule && ! $yesterdaySchedule->is_closed && $yesterdaySchedule->opens_at && $yesterdaySchedule->closes_at) {
            if ($yesterdaySchedule->opens_at > $yesterdaySchedule->closes_at && $currentTime <= $yesterdaySchedule->closes_at) {
                return true;
            }
        }

        return false;
    }
}
