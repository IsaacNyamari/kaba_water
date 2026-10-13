<?php

namespace App\Services\Accounting\Postings;

use App\Models\Asset;
use Carbon\CarbonInterface;

/**
 * Monthly depreciation of a fixed asset (non-cash).
 *   Dr 5400 Depreciation Expense
 *   Cr 1510 Accumulated Depreciation
 * One entry per asset per month (source_event "depreciation.YYYY-MM").
 */
class DepreciationPosted extends Posting
{
    public function __construct(public Asset $asset, public CarbonInterface $month, public string $amount) {}

    public function lines(): array
    {
        $desc = "Depreciation {$this->month->format('M Y')} — {$this->asset->name}";

        return [
            self::dr(self::code('depreciation'), $this->amount, ['description' => $desc]),
            self::cr(self::code('accumulated_depreciation'), $this->amount, ['description' => $desc]),
        ];
    }

    public function meta(): array
    {
        return [
            'vendor_id' => $this->asset->vendor_id,
            'date' => $this->month->copy()->endOfMonth(),
            'reference' => $this->asset->identifier,
            'memo' => "Depreciation {$this->month->format('M Y')}: {$this->asset->name}",
            'source_type' => Asset::class,
            'source_id' => $this->asset->id,
            'source_event' => 'depreciation.'.$this->month->format('Y-m'),
        ];
    }
}
