<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('platform.manage_plans');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('platform.manage_plans');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('platform.manage_plans');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('platform.manage_plans');
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('platform.manage_plans') && ! $plan->vendors()->exists();
    }
}
