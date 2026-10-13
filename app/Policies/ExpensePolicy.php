<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.expenses.view', 'vendor.expenses.manage']);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->vendor_id === $expense->vendor_id
            && $user->hasAnyPermission(['vendor.expenses.view', 'vendor.expenses.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.expenses.manage');
    }
    public function update(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $user->hasPermissionTo('vendor.expenses.manage');
    }
    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
