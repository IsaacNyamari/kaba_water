<?php

namespace App\Policies;

use App\Models\Liability;
use App\Models\User;

class LiabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.liabilities.view', 'vendor.liabilities.manage']);
    }

    public function view(User $user, Liability $liability): bool
    {
        return $user->vendor_id === $liability->vendor_id
            && $user->hasAnyPermission(['vendor.liabilities.view', 'vendor.liabilities.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.liabilities.manage');
    }
    public function update(User $user, Liability $liability): bool
    {
        return $this->view($user, $liability) && $user->hasPermissionTo('vendor.liabilities.manage');
    }
    public function delete(User $user, Liability $liability): bool
    {
        return $this->update($user, $liability);
    }
}
