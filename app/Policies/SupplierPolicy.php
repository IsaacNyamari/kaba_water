<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.suppliers.view', 'vendor.suppliers.manage']);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->vendor_id === $supplier->vendor_id
            && $user->hasAnyPermission(['vendor.suppliers.view', 'vendor.suppliers.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.suppliers.manage');
    }
    public function update(User $user, Supplier $supplier): bool
    {
        return $this->view($user, $supplier) && $user->hasPermissionTo('vendor.suppliers.manage');
    }
    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->update($user, $supplier);
    }
}
