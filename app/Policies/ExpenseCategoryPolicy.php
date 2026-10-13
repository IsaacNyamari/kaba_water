<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'vendor.expenses.view',
            'vendor.expenses.manage',
            'vendor.expense_categories.manage',
        ]);
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->vendor_id === $category->vendor_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.expense_categories.manage')
            || $user->hasPermissionTo('vendor.expenses.manage');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->vendor_id === $category->vendor_id
            && $this->create($user);
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->update($user, $category);
    }
}
