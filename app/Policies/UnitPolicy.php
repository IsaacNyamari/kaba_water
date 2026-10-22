<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

/** Houses are managed with the same permissions as the clients (tenants) living in them. */
class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.clients.view', 'vendor.clients.manage']);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->vendor_id === $unit->vendor_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.clients.manage');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit) && $user->hasPermissionTo('vendor.clients.manage');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->update($user, $unit);
    }
}
