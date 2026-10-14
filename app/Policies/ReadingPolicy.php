<?php

namespace App\Policies;

use App\Models\Reading;
use App\Models\User;

class ReadingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.readings.view', 'vendor.readings.manage']);
    }

    public function view(User $user, Reading $reading): bool
    {
        if ($user->hasRole('client')) {
            return $reading->meter?->client?->user_id === $user->id
                && $user->hasPermissionTo('client.readings.view');
        }

        return $user->vendor_id === $reading->vendor_id
            && $user->hasAnyPermission(['vendor.readings.view', 'vendor.readings.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.readings.manage')
            || $user->hasPermissionTo('vendor.readings.create')
            || $user->hasPermissionTo('client.readings.create');
    }

    public function update(User $user, Reading $reading): bool
    {
        return $user->hasPermissionTo('vendor.readings.manage')
            && $user->vendor_id === $reading->vendor_id;
    }

    public function delete(User $user, Reading $reading): bool
    {
        return $this->update($user, $reading);
    }
}
