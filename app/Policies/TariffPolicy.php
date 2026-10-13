<?php

namespace App\Policies;

use App\Models\Tariff;
use App\Models\User;

class TariffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.tariffs.view', 'vendor.tariffs.manage']);
    }

    public function view(User $user, Tariff $tariff): bool
    {
        return $user->vendor_id === $tariff->vendor_id
            && $user->hasAnyPermission(['vendor.tariffs.view', 'vendor.tariffs.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.tariffs.manage');
    }
    public function update(User $user, Tariff $tariff): bool
    {
        return $this->view($user, $tariff) && $user->hasPermissionTo('vendor.tariffs.manage');
    }
    public function delete(User $user, Tariff $tariff): bool
    {
        return $this->update($user, $tariff);
    }
}
