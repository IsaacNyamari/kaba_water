<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

/** Properties are managed with the same permissions as the clients (tenants) living in them. */
class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.clients.view', 'vendor.clients.manage']);
    }

    public function view(User $user, Property $property): bool
    {
        return $user->vendor_id === $property->vendor_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.clients.manage');
    }

    public function update(User $user, Property $property): bool
    {
        return $this->view($user, $property) && $user->hasPermissionTo('vendor.clients.manage');
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->update($user, $property);
    }
}
