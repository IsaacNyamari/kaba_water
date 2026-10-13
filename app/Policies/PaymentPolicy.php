<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('client')) return $user->hasPermissionTo('client.payments.view');
        return $user->hasAnyPermission(['vendor.payments.view', 'vendor.payments.manage']);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasRole('client')) {
            return $payment->client?->user_id === $user->id
                && $user->hasPermissionTo('client.payments.view');
        }

        return $user->vendor_id === $payment->vendor_id
            && $user->hasAnyPermission(['vendor.payments.view', 'vendor.payments.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.payments.manage');
    }
    public function delete(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment) && $user->hasPermissionTo('vendor.payments.manage');
    }
}
