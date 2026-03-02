<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id;
    }
}
