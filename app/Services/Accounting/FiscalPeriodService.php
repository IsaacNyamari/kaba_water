<?php

namespace App\Services\Accounting;

use App\Models\FiscalPeriod;
use App\Models\FiscalPeriodEvent;
use App\Models\User;
use App\Services\Accounting\Exceptions\ClosedPeriodException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalPeriodService
{
    /** The period containing today for a vendor (null if none defined). */
    public function currentPeriod(int $vendorId, ?CarbonInterface $today = null): ?FiscalPeriod
    {
        return FiscalPeriod::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->forDate(($today ?? now())->toDateString())
            ->first();
    }

    /** True when $date sits inside ANY closed period of the vendor. */
    public function isClosed(int $vendorId, CarbonInterface|string $date): bool
    {
        $d = $date instanceof CarbonInterface ? $date->toDateString() : CarbonImmutable::parse($date)->toDateString();

        return FiscalPeriod::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->where('is_closed', true)
            ->forDate($d)
            ->exists();
    }

    /**
     * Guard used by LedgerService::post() and the auto-posting observers.
     *
     * @throws ClosedPeriodException
     */
    public function assertOpen(int $vendorId, CarbonInterface|string $date): void
    {
        if ($this->isClosed($vendorId, $date)) {
            $d = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;

            throw new ClosedPeriodException("{$d} falls inside a closed fiscal period. Reopen the period or use a later date.");
        }
    }

    /**
     * Create the four calendar quarters of $year (skips ones that exist).
     *
     * @return list<FiscalPeriod>
     */
    public function createQuarters(int $vendorId, int $year): array
    {
        $created = [];

        foreach ([1, 2, 3, 4] as $q) {
            $start = CarbonImmutable::create($year, ($q - 1) * 3 + 1, 1)->startOfDay();
            $end = $start->addMonths(2)->endOfMonth();

            $created[] = $this->create($vendorId, "{$year}-Q{$q}", $start, $end, skipIfExists: true);
        }

        return $created;
    }

    /** Create a period, refusing overlaps with existing periods. */
    public function create(int $vendorId, string $name, CarbonInterface $start, CarbonInterface $end, bool $skipIfExists = false): FiscalPeriod
    {
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'The period must end after it starts.']);
        }

        $existing = FiscalPeriod::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->whereDate('start_date', $start->toDateString())
            ->whereDate('end_date', $end->toDateString())
            ->first();

        if ($existing) {
            if ($skipIfExists) {
                return $existing;
            }
            throw ValidationException::withMessages(['start_date' => 'That period already exists.']);
        }

        $overlaps = FiscalPeriod::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => 'Periods may not overlap.']);
        }

        return FiscalPeriod::withoutGlobalScope('vendor')->create([
            'vendor_id' => $vendorId,
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    /** Close a period: no entry may be dated inside it afterwards. */
    public function close(FiscalPeriod $period, ?User $by = null, ?string $reason = null): FiscalPeriod
    {
        if ($period->is_closed) {
            throw ValidationException::withMessages(['period' => "{$period->name} is already closed."]);
        }

        DB::transaction(function () use ($period, $by, $reason) {
            $period->update(['is_closed' => true, 'closed_at' => now(), 'closed_by' => $by?->id]);
            FiscalPeriodEvent::create(['fiscal_period_id' => $period->id, 'user_id' => $by?->id, 'action' => 'closed', 'reason' => $reason]);
        });

        return $period;
    }

    /**
     * Reopen a closed period. Only users with accounting.reopen-period may do
     * this, a reason is required, and the action is written to the audit trail.
     *
     * @throws AuthorizationException
     */
    public function reopen(FiscalPeriod $period, User $by, string $reason): FiscalPeriod
    {
        if (! $by->checkPermissionTo('accounting.reopen-period')) {
            throw new AuthorizationException('You are not allowed to reopen fiscal periods.');
        }

        if (! $period->is_closed) {
            throw ValidationException::withMessages(['period' => "{$period->name} is not closed."]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for reopening — it is kept in the audit trail.']);
        }

        DB::transaction(function () use ($period, $by, $reason) {
            $period->update(['is_closed' => false, 'closed_at' => null, 'closed_by' => null]);
            FiscalPeriodEvent::create(['fiscal_period_id' => $period->id, 'user_id' => $by->id, 'action' => 'reopened', 'reason' => $reason]);
        });

        return $period;
    }
}
