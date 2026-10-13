<?php

namespace App\Models\Concerns;

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Services\Accounting\ChartOfAccountsService;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Accounting relations for Vendor, plus lazy chart-of-accounts seeding. */
trait HasAccounting
{
    public function chartOfAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class)->withoutGlobalScope('vendor');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class)->withoutGlobalScope('vendor');
    }

    public function fiscalPeriods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class)->withoutGlobalScope('vendor');
    }

    /** Seed the standard chart the first time accounting is used. */
    public function ensureChartOfAccounts(): void
    {
        app(ChartOfAccountsService::class)->ensureFor($this);
    }
}
