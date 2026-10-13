<?php

namespace App\Actions\Accounting;

use App\Models\Asset;
use App\Models\JournalLine;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\DepreciationPosted;
use App\Support\Money\MoneyMath;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Post one month of straight-line depreciation for every active asset:
 *   monthly = cost × rate% ÷ 12, never taking the total posted past (cost − salvage).
 * Idempotent: each asset gets at most one entry per month.
 */
class PostMonthlyDepreciation
{
    public function __construct(protected LedgerService $ledger) {}

    /** @return int number of assets depreciated */
    public function handle(int $vendorId, CarbonImmutable $month): int
    {
        $month = $month->startOfMonth();
        $count = 0;

        $assets = Asset::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->where('depreciation_rate', '>', 0)
            ->whereDate('purchase_date', '<=', $month->endOfMonth()->toDateString())
            ->get();

        foreach ($assets as $asset) {
            $amount = $this->amountFor($asset);
            if (! MoneyMath::isPositive($amount)) {
                continue;
            }

            $event = new DepreciationPosted($asset, $month, $amount);
            if ($this->ledger->findPosted($event->meta())) {
                continue;
            }

            $this->ledger->postEvent($event);
            $count++;
        }

        return $count;
    }

    protected function amountFor(Asset $asset): string
    {
        // cost × rate% ÷ 12, rounded half-up — exact decimal maths via brick/math
        $monthly = BigDecimal::of((string) $asset->purchase_cost)
            ->multipliedBy((string) $asset->depreciation_rate)
            ->dividedBy(1200, 2, RoundingMode::HalfUp);

        $depreciable = MoneyMath::sub((string) $asset->purchase_cost, (string) $asset->salvage_value);
        $already = $this->postedSoFar($asset);
        $remaining = MoneyMath::sub($depreciable, $already);

        if (! MoneyMath::isPositive($remaining)) {
            return '0.00';
        }

        return MoneyMath::compare((string) $monthly, $remaining) > 0 ? $remaining : MoneyMath::str((string) $monthly);
    }

    /** Net depreciation already posted for this asset (reversals cancel out). */
    protected function postedSoFar(Asset $asset): string
    {
        $code = config('accounting.accounts.depreciation');

        $lines = JournalLine::query()
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'journal_lines.account_id')
            ->where('je.vendor_id', $asset->vendor_id)
            ->whereNotNull('je.posted_at')
            ->where('je.source_type', Asset::class)
            ->where('je.source_id', $asset->id)
            ->where('a.code', $code)
            ->get(['journal_lines.debit', 'journal_lines.credit']);

        return MoneyMath::sub(MoneyMath::sumColumn($lines, 'debit'), MoneyMath::sumColumn($lines, 'credit'));
    }
}
