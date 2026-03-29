<?php

namespace App\Policies;

use App\Models\ModifierGroupTemplate;
use App\Models\User;

class ModifierGroupTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, ModifierGroupTemplate $template): bool
    {
        return $user->restaurant_id === $template->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, ModifierGroupTemplate $template): bool
    {
        return $user->restaurant_id === $template->restaurant_id;
    }

    public function delete(User $user, ModifierGroupTemplate $template): bool
    {
        return $user->restaurant_id === $template->restaurant_id;
    }
}
