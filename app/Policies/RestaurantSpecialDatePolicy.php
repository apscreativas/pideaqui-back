<?php

namespace App\Policies;

use App\Models\RestaurantSpecialDate;
use App\Models\User;

class RestaurantSpecialDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, RestaurantSpecialDate $specialDate): bool
    {
        return $user->restaurant_id === $specialDate->restaurant_id;
    }

    public function delete(User $user, RestaurantSpecialDate $specialDate): bool
    {
        return $user->restaurant_id === $specialDate->restaurant_id;
    }
}
