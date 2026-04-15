<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /** Only restaurant admins can access the expenses module. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->restaurant_id !== null;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $expense->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->restaurant_id !== null;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $expense->restaurant_id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $expense->restaurant_id;
    }
}
