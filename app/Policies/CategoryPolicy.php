<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, Category $category): bool
    {
        return $user->restaurant_id === $category->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->restaurant_id === $category->restaurant_id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->restaurant_id === $category->restaurant_id;
    }
}
