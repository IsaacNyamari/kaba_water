<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('client')) return $user->hasPermissionTo('client.work_orders.view');

        return $user->hasAnyPermission(['vendor.work_orders.view', 'vendor.work_orders.manage']);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        if ($user->hasRole('client')) {
            return $workOrder->client?->user_id === $user->id
                && $user->hasPermissionTo('client.work_orders.view');
        }

        return $user->vendor_id === $workOrder->vendor_id
            && $user->hasAnyPermission(['vendor.work_orders.view', 'vendor.work_orders.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.work_orders.manage');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->vendor_id === $workOrder->vendor_id
            && $user->hasPermissionTo('vendor.work_orders.manage');
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }

    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }

    public function complete(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }
}
