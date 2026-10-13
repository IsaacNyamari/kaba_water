<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.assets.view', 'vendor.assets.manage']);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->vendor_id === $asset->vendor_id
            && $user->hasAnyPermission(['vendor.assets.view', 'vendor.assets.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.assets.manage');
    }
    public function update(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset) && $user->hasPermissionTo('vendor.assets.manage');
    }
    public function delete(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
