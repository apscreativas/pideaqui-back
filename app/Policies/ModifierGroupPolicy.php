<?php

namespace App\Policies;

use App\Models\ModifierGroup;
use App\Models\User;

class ModifierGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->restaurant_id === $modifierGroup->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->restaurant_id === $modifierGroup->restaurant_id;
    }

    public function delete(User $user, ModifierGroup $modifierGroup): bool
    {
        return $user->restaurant_id === $modifierGroup->restaurant_id;
    }
}
