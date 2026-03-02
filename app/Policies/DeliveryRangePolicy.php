<?php

namespace App\Policies;

use App\Models\DeliveryRange;
use App\Models\User;

class DeliveryRangePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, DeliveryRange $deliveryRange): bool
    {
        return $user->restaurant_id === $deliveryRange->restaurant_id;
    }

    public function delete(User $user, DeliveryRange $deliveryRange): bool
    {
        return $user->restaurant_id === $deliveryRange->restaurant_id;
    }
}
