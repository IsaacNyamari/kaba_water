<?php

namespace App\Services\Accounting\Postings;

use App\Models\Invoice;
use App\Support\Money\MoneyMath;

/**
 * An invoice is issued (sent) to a customer.
 *   Dr 1100 Accounts Receivable   total (gross)
 *   Cr 4000 Water Revenue         subtotal (net)
 *   Cr 2100 VAT Payable           tax
 */
class InvoiceIssued extends Posting
{
    public const EVENT = 'invoice.issued';

    public function __construct(public Invoice $invoice) {}

    public function lines(): array
    {
        $i = $this->invoice;
        $tags = ['client_id' => $i->client_id, 'meter_id' => $i->reading?->meter_id, 'description' => "Invoice {$i->number}"];

        // Net = total − tax, so the entry balances even if subtotal was rounded separately.
        $net = MoneyMath::sub((string) $i->total, (string) $i->tax);

        return [
            self::dr(self::code('receivables'), (string) $i->total, $tags),
            self::cr(self::code('water_revenue'), $net, $tags),
            self::cr(self::code('vat_payable'), (string) $i->tax, $tags),
        ];
    }

    public function meta(): array
    {
        $i = $this->invoice;

        return [
            'vendor_id' => $i->vendor_id,
            'date' => $i->issued_at ?? now(),
            'reference' => $i->number,
            'memo' => "Invoice {$i->number} issued",
            'source_type' => Invoice::class,
            'source_id' => $i->id,
            'source_event' => self::EVENT,
        ];
    }
}
