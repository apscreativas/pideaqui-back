<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\LimitService;
use App\Support\BillingMessages;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'restaurant_id' => $user->restaurant_id ?? null,
                    'role' => $user->role ?? 'admin',
                    'is_admin' => method_exists($user, 'isAdmin') ? $user->isAdmin() : true,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'billing' => fn () => $this->getBillingData($user),
            'menu_base_url' => fn () => PlatformSetting::get('public_menu_base_url') ?: config('app.url'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getBillingData(?Authenticatable $user): ?array
    {
        if (! $user instanceof User || ! $user->restaurant_id) {
            return null;
        }

        $restaurant = $user->restaurant;

        if (! $restaurant) {
            return null;
        }

        $limits = app(LimitService::class);
        $blockReason = $restaurant->operationalBlockReason($limits);

        return [
            'status' => $restaurant->status ?? 'active',
            'billing_mode' => $restaurant->billing_mode ?? 'manual',
            'plan_name' => $restaurant->plan?->name,
            'grace_period_ends_at' => $restaurant->grace_period_ends_at?->toIso8601String(),
            'grace_days_remaining' => $this->graceDaysRemaining($restaurant),
            'subscription_ends_at' => $restaurant->subscription_ends_at?->toIso8601String(),
            'must_show_billing' => $restaurant->mustShowBilling(),
            'can_operate' => $blockReason === null,
            'block_reason' => $blockReason,
            'block_message' => BillingMessages::operational($restaurant, $blockReason),
        ];
    }

    private function graceDaysRemaining(\App\Models\Restaurant $restaurant): ?int
    {
        if ($restaurant->status !== 'grace_period' || ! $restaurant->grace_period_ends_at) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInDays($restaurant->grace_period_ends_at, false)));
    }
}
