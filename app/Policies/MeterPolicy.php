<?php

namespace App\Policies;

use App\Models\Meter;
use App\Models\User;

class MeterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.meters.view', 'vendor.meters.manage']);
    }

    public function view(User $user, Meter $meter): bool
    {
        return $user->vendor_id === $meter->vendor_id
            && $user->hasAnyPermission(['vendor.meters.view', 'vendor.meters.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.meters.manage');
    }
    public function update(User $user, Meter $meter): bool
    {
        // Viewing is not enough to edit — require the manage permission.
        return $this->view($user, $meter) && $user->hasPermissionTo('vendor.meters.manage');
    }
    public function delete(User $user, Meter $meter): bool
    {
        return $this->update($user, $meter);
    }
}
