<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('platform.manage_vendors');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('platform.manage_vendors');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('platform.manage_vendors');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('platform.manage_vendors');
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('platform.manage_vendors');
    }

    public function suspend(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('platform.suspend_vendor');
    }
}
