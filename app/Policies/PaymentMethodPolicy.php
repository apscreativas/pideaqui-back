<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->restaurant_id === $paymentMethod->restaurant_id;
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->restaurant_id === $paymentMethod->restaurant_id;
    }
}
