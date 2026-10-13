<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('client')) return $user->hasPermissionTo('client.invoices.view');
        return $user->hasAnyPermission(['vendor.invoices.view', 'vendor.invoices.manage']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('client')) {
            // Clients never see drafts — those are still being prepared by the vendor.
            return $invoice->client?->user_id === $user->id
                && $invoice->status !== \App\Enums\InvoiceStatus::Draft
                && $user->hasPermissionTo('client.invoices.view');
        }

        return $user->vendor_id === $invoice->vendor_id
            && $user->hasAnyPermission(['vendor.invoices.view', 'vendor.invoices.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.invoices.manage');
    }
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $user->hasPermissionTo('vendor.invoices.manage');
    }
    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
    public function send(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
    public function downloadPdf(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
