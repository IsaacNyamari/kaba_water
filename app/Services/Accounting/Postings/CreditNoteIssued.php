<?php

namespace App\Services\Accounting\Postings;

use App\Models\Invoice;
use App\Support\Money\MoneyMath;

/**
 * An issued invoice is voided — the exact reverse of InvoiceIssued.
 *   Dr 4xxx Revenue              net (same split as the invoice)
 *   Dr 2100 VAT Payable          tax
 *   Cr 1100 Accounts Receivable  total
 */
class CreditNoteIssued extends Posting
{
    public const EVENT = 'invoice.voided';

    public function __construct(public Invoice $invoice, public ?\DateTimeInterface $date = null) {}

    public function lines(): array
    {
        $i = $this->invoice;
        $tags = ['client_id' => $i->client_id, 'meter_id' => $i->reading?->meter_id, 'description' => "Credit note for {$i->number}"];
        $net = MoneyMath::sub((string) $i->total, (string) $i->tax);

        $revenue = [];
        foreach (InvoiceIssued::revenueByAccount($i, $net) as $code => $amount) {
            $revenue[] = self::dr($code, $amount, $tags);
        }

        return [
            ...$revenue,
            self::dr(self::code('vat_payable'), (string) $i->tax, $tags),
            self::cr(self::code('receivables'), (string) $i->total, $tags),
        ];
    }

    public function meta(): array
    {
        $i = $this->invoice;
        $date = $this->date ?? now();
        // Never before the invoice itself.
        if ($i->issued_at && $date < $i->issued_at) {
            $date = $i->issued_at;
        }

        return [
            'vendor_id' => $i->vendor_id,
            'date' => $date,
            'reference' => $i->number,
            'memo' => "Invoice {$i->number} voided (credit note)",
            'source_type' => Invoice::class,
            'source_id' => $i->id,
            'source_event' => self::EVENT,
        ];
    }
}
