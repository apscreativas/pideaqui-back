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
        return $user->restaurant_id === $order->restaurant_id
            && $this->canAccessBranch($user, $order->branch_id);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $this->canAccessBranch($user, $order->branch_id);
    }

    public function edit(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $order->isEditable()
            && $this->canAccessBranch($user, $order->branch_id);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $order->isCancellable()
            && $this->canAccessBranch($user, $order->branch_id);
    }

    /**
     * Admins can access all branches. Operators can only access assigned branches.
     */
    private function canAccessBranch(User $user, ?int $branchId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($branchId === null) {
            return false;
        }

        $allowed = $user->allowedBranchIds();

        return in_array($branchId, $allowed, true);
    }
}
