<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->restaurant_id === $branch->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->restaurant_id === $branch->restaurant_id;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->restaurant_id === $branch->restaurant_id;
    }
}
