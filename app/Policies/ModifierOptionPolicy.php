<?php

namespace App\Policies;

use App\Models\ModifierOption;
use App\Models\User;

class ModifierOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, ModifierOption $modifierOption): bool
    {
        return $user->restaurant_id === $modifierOption->modifierGroup->restaurant_id;
    }

    public function delete(User $user, ModifierOption $modifierOption): bool
    {
        return $user->restaurant_id === $modifierOption->modifierGroup->restaurant_id;
    }
}
