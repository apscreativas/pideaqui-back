<?php

namespace App\Services\Onboarding;

use App\Models\BillingAudit;
use App\Models\BillingSetting;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Onboarding\Dto\ProvisionRestaurantData;
use App\Services\SlugSuggester;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class RestaurantProvisioningService
{
    public function __construct(private readonly SlugSuggester $slugs) {}

    public function provision(ProvisionRestaurantData $data): Restaurant
    {
        // Retry once if a concurrent signup takes the slug between our
        // availability check and the insert. The second attempt asks the
        // suggester for a fresh slug based on the original name.
        $attempts = 0;
        $maxAttempts = 2;

        while (true) {
            $attempts++;
            try {
                return DB::transaction(function () use ($data): Restaurant {
                    $restaurant = $this->createRestaurant($data);
                    $this->createAdminUser($restaurant, $data);
                    $this->seedPaymentMethods($restaurant->id);
                    $this->logCreation($restaurant, $data);

                    return $restaurant;
                });
            } catch (QueryException $e) {
                $isSlugCollision = $this->isSlugUniqueViolation($e);
                if (! $isSlugCollision || $attempts >= $maxAttempts) {
                    throw $e;
                }
                // Force auto-generation on the second attempt so the retry
                // always gets a fresh, currently-available slug.
                $data = new ProvisionRestaurantData(
                    source: $data->source,
                    restaurantName: $data->restaurantName,
                    adminName: $data->adminName,
                    adminEmail: $data->adminEmail,
                    adminPassword: $data->adminPassword,
                    billingMode: $data->billingMode,
                    ordersLimit: $data->ordersLimit,
                    maxBranches: $data->maxBranches,
                    ordersLimitStart: $data->ordersLimitStart,
                    ordersLimitEnd: $data->ordersLimitEnd,
                    actorId: $data->actorId,
                    ipAddress: $data->ipAddress,
                    slug: null,
                );
            }
        }
    }

    private function createRestaurant(ProvisionRestaurantData $data): Restaurant
    {
        $slug = $this->resolveSlug($data);

        if ($data->billingMode === 'manual') {
            return Restaurant::create([
                'name' => $data->restaurantName,
                'slug' => $slug,
                'is_active' => true,
                'billing_mode' => 'manual',
                'plan_id' => null,
                'status' => 'active',
                'orders_limit' => $data->ordersLimit,
                'orders_limit_start' => $data->ordersLimitStart,
                'orders_limit_end' => $data->ordersLimitEnd,
                'max_branches' => $data->maxBranches,
                'allows_delivery' => false,
                'allows_pickup' => true,
                'allows_dine_in' => false,
                'signup_source' => $data->source,
            ]);
        }

        $gracePlan = Plan::gracePlan();
        $graceDays = BillingSetting::getInt('initial_grace_period_days', 14);

        return Restaurant::create([
            'name' => $data->restaurantName,
            'slug' => $slug,
            'is_active' => true,
            'billing_mode' => 'subscription',
            'plan_id' => $gracePlan?->id,
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays($graceDays),
            'orders_limit' => $gracePlan?->orders_limit ?? 50,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
            'max_branches' => $gracePlan?->max_branches ?? 1,
            'allows_delivery' => false,
            'allows_pickup' => true,
            'allows_dine_in' => false,
            'signup_source' => $data->source,
        ]);
    }

    /**
     * Use the user-provided slug when it is still free and valid; fall back
     * to auto-generation otherwise. Keeps compatibility with flows that
     * do not pass a slug (e.g. older tests and seeders).
     */
    private function resolveSlug(ProvisionRestaurantData $data): string
    {
        $provided = $data->slug;

        if (is_string($provided) && $provided !== '') {
            $sanitized = $this->slugs->sanitize($provided);
            if ($sanitized !== '' && ! $this->slugs->isReserved($sanitized) && ! $this->slugs->isTaken($sanitized)) {
                return $sanitized;
            }
        }

        return $this->slugs->generateUnique($data->restaurantName);
    }

    private function createAdminUser(Restaurant $restaurant, ProvisionRestaurantData $data): User
    {
        $user = new User([
            'name' => $data->adminName,
            'email' => $data->adminEmail,
            'password' => $data->adminPassword,
        ]);
        $user->role = 'admin';
        $user->restaurant_id = $restaurant->id;

        if ($data->source === 'super_admin') {
            $user->email_verified_at = now();
        }

        $user->save();

        return $user;
    }

    private function seedPaymentMethods(int $restaurantId): void
    {
        PaymentMethod::insert([
            ['restaurant_id' => $restaurantId, 'type' => 'cash', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => $restaurantId, 'type' => 'terminal', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => $restaurantId, 'type' => 'transfer', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function logCreation(Restaurant $restaurant, ProvisionRestaurantData $data): void
    {
        BillingAudit::log(
            action: 'restaurant_created',
            restaurantId: $restaurant->id,
            actorType: $data->source,
            actorId: $data->actorId,
            payload: [
                'billing_mode' => $data->billingMode,
                'plan' => $restaurant->plan?->name ?? 'manual',
            ],
            ipAddress: $data->ipAddress,
        );
    }

    private function isSlugUniqueViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'restaurants_slug_unique')
            || str_contains($message, 'slug')
                && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
