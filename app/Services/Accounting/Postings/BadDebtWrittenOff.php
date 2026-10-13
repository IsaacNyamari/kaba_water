<?php

namespace App\Services\Accounting\Postings;

use App\Models\Invoice;

/**
 * An uncollectable invoice balance is written off.
 *   Dr 5300 Bad Debt
 *   Cr 1100 Accounts Receivable
 */
class BadDebtWrittenOff extends Posting
{
    public const EVENT = 'invoice.written_off';

    public function __construct(public Invoice $invoice, public string $amount, public ?\DateTimeInterface $date = null) {}

    public function lines(): array
    {
        $tags = ['client_id' => $this->invoice->client_id, 'description' => "Write-off {$this->invoice->number}"];

        return [
            self::dr(self::code('bad_debt'), $this->amount, $tags),
            self::cr(self::code('receivables'), $this->amount, $tags),
        ];
    }

    public function meta(): array
    {
        return [
            'vendor_id' => $this->invoice->vendor_id,
            'date' => $this->date ?? now(),
            'reference' => $this->invoice->number,
            'memo' => "Bad debt written off: {$this->invoice->number}",
            'source_type' => Invoice::class,
            'source_id' => $this->invoice->id,
            'source_event' => self::EVENT,
        ];
    }
}
