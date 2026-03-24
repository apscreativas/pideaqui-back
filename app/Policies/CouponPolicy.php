<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->restaurant_id === $coupon->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->restaurant_id === $coupon->restaurant_id;
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->restaurant_id === $coupon->restaurant_id;
    }
}
