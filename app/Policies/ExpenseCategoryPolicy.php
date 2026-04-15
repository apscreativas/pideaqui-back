<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->restaurant_id !== null;
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $category->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->restaurant_id !== null;
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $category->restaurant_id;
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $category->restaurant_id;
    }
}
