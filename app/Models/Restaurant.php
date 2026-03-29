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
        'primary_color',
        'secondary_color',
        'default_product_image',
        'text_color',
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

    protected $appends = ['logo_url', 'default_product_image_url'];

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo_path
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($this->logo_path)
                : null,
        );
    }

    protected function defaultProductImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->default_product_image
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($this->default_product_image)
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

    public function specialDates(): HasMany
    {
        return $this->hasMany(RestaurantSpecialDate::class)->orderBy('date');
    }

    /**
     * @return array<int, string>
     */
    public function routeNotificationForMail(): array
    {
        return $this->users->pluck('email')->all();
    }

    /**
     * Resolve the effective schedule for a given date.
     * Priority: special_date entry > regular weekday schedule.
     *
     * @return array{source: string, opens_at: ?string, closes_at: ?string, label: ?string}
     */
    public function getResolvedScheduleForDate(Carbon $date): array
    {
        $specialDate = RestaurantSpecialDate::findForDate($this->id, $date);

        if ($specialDate) {
            if ($specialDate->type === 'closed') {
                return [
                    'source' => 'closed',
                    'opens_at' => null,
                    'closes_at' => null,
                    'label' => $specialDate->label,
                ];
            }

            // type = 'special'
            return [
                'source' => 'special',
                'opens_at' => $specialDate->opens_at ? substr($specialDate->opens_at, 0, 5) : null,
                'closes_at' => $specialDate->closes_at ? substr($specialDate->closes_at, 0, 5) : null,
                'label' => $specialDate->label,
            ];
        }

        // Fall back to regular weekday schedule.
        $schedule = $this->schedules->firstWhere('day_of_week', $date->dayOfWeek);

        if (! $schedule || $schedule->is_closed || ! $schedule->opens_at || ! $schedule->closes_at) {
            return [
                'source' => 'regular',
                'opens_at' => null,
                'closes_at' => null,
                'label' => null,
            ];
        }

        return [
            'source' => 'regular',
            'opens_at' => substr($schedule->opens_at, 0, 5),
            'closes_at' => substr($schedule->closes_at, 0, 5),
            'label' => null,
        ];
    }

    public function isCurrentlyOpen(): bool
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');

        // Resolve today's effective schedule (special date > regular).
        $today = $this->getResolvedScheduleForDate($now);

        if ($today['source'] === 'closed') {
            return false;
        }

        if ($today['opens_at'] && $today['closes_at']) {
            $opens = $today['opens_at'].':00';
            $closes = $today['closes_at'].':00';

            if ($opens > $closes) {
                // Overnight (e.g. 22:00–02:00): if current time >= opens, we're in the first part.
                if ($currentTime >= $opens) {
                    return true;
                }
            } elseif ($currentTime >= $opens && $currentTime <= $closes) {
                return true;
            }
        }

        // Check yesterday's overnight carryover.
        $yesterday = $now->copy()->subDay();
        $yesterdaySchedule = $this->getResolvedScheduleForDate($yesterday);

        if ($yesterdaySchedule['source'] === 'closed' || ! $yesterdaySchedule['opens_at'] || ! $yesterdaySchedule['closes_at']) {
            return false;
        }

        $yOpens = $yesterdaySchedule['opens_at'].':00';
        $yCloses = $yesterdaySchedule['closes_at'].':00';

        if ($yOpens > $yCloses && $currentTime <= $yCloses) {
            return true;
        }

        return false;
    }
}
