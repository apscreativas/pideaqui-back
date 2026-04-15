<?php

namespace App\Policies;

use App\Models\PosSale;
use App\Models\User;

class PosSalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->restaurant_id !== null;
    }

    public function view(User $user, PosSale $posSale): bool
    {
        return $user->restaurant_id === $posSale->restaurant_id
            && $this->canAccessBranch($user, $posSale->branch_id);
    }

    public function update(User $user, PosSale $posSale): bool
    {
        return $user->restaurant_id === $posSale->restaurant_id
            && $this->canAccessBranch($user, $posSale->branch_id);
    }

    public function cancel(User $user, PosSale $posSale): bool
    {
        return $user->restaurant_id === $posSale->restaurant_id
            && $posSale->isCancellable()
            && $this->canAccessBranch($user, $posSale->branch_id);
    }

    private function canAccessBranch(User $user, ?int $branchId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($branchId === null) {
            return false;
        }

        return in_array($branchId, $user->allowedBranchIds(), true);
    }
}
