<?php

namespace App\Livewire\Concerns;

use App\Models\Vendor;
use Carbon\CarbonImmutable;

/**
 * Shared bits for vendor accounting pages: vendor id, permission checks and
 * lazy chart-of-accounts seeding ("seeded per vendor on first use").
 */
trait UsesAccounting
{
    protected function vendorId(): int
    {
        $id = auth()->user()?->vendor_id;
        abort_unless($id, 403, 'No vendor workspace attached.');

        return (int) $id;
    }

    protected function bootAccounting(string $permission = 'accounting.view'): void
    {
        $this->authorize($permission);
        Vendor::findOrFail($this->vendorId())->ensureChartOfAccounts();
    }

    protected function currency(): string
    {
        return Vendor::whereKey($this->vendorId())->value('currency') ?: config('accounting.currency', 'KES');
    }

    /** Parse a Y-m-d filter value, falling back to $default on bad input. */
    protected function dateOr(?string $value, CarbonImmutable $default): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: $default : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
