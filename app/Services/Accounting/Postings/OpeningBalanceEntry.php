<?php

namespace App\Services\Accounting\Postings;

use App\Models\OpeningBalance;

/**
 * Free-form opening balances from the wizard. The wizard supplies the lines
 * (cash, receivables, fixed assets, payables…) with Owner's Equity as the
 * balancing figure.
 */
class OpeningBalanceEntry extends Posting
{
    public const EVENT = 'opening_balance';

    /** @param list<array<string, mixed>> $lines */
    public function __construct(public OpeningBalance $opening, public array $entryLines) {}

    public function lines(): array
    {
        return $this->entryLines;
    }

    public function meta(): array
    {
        return [
            'vendor_id' => $this->opening->vendor_id,
            'date' => $this->opening->as_of_date,
            'reference' => 'OPENING',
            'memo' => 'Opening balances as of '.$this->opening->as_of_date->format('d M Y'),
            'source_type' => OpeningBalance::class,
            'source_id' => $this->opening->id,
            'source_event' => self::EVENT,
        ];
    }
}
