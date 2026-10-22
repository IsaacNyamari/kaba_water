<?php

namespace App\Services\Accounting\Postings;

use App\Models\Invoice;
use App\Support\Money\MoneyMath;

/**
 * An invoice is issued (sent) to a customer.
 *   Dr 1100 Accounts Receivable   total (gross)
 *   Cr 4xxx Revenue               subtotal (net), split by line category:
 *                                 4000 Water Revenue, 4300 Rental Income, 4400 Service Charges
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

        $revenue = [];
        foreach (self::revenueByAccount($i, $net) as $code => $amount) {
            $revenue[] = self::cr($code, $amount, $tags);
        }

        return [
            self::dr(self::code('receivables'), (string) $i->total, $tags),
            ...$revenue,
            self::cr(self::code('vat_payable'), (string) $i->tax, $tags),
        ];
    }

    /**
     * Split the invoice's net revenue across revenue accounts by line
     * category (config accounting.invoice_item_accounts): rent lines to
     * Rental Income, service-charge lines to Service Charges. Whatever is
     * left — uncategorised and water lines, or any rounding gap between the
     * lines and the net — goes to Water Revenue, so the split always sums to
     * exactly $net and the entry balances.
     *
     * @return array<string, string> account code => amount
     */
    public static function revenueByAccount(Invoice $invoice, string $net): array
    {
        $default = self::code('water_revenue');
        $mapped = [];

        foreach ($invoice->items as $item) {
            $code = (string) config("accounting.invoice_item_accounts.{$item->category}", $default);
            if ($code !== $default) {
                $mapped[$code] = MoneyMath::add($mapped[$code] ?? '0', (string) $item->total);
            }
        }

        $rest = MoneyMath::sub($net, MoneyMath::add('0', ...array_values($mapped)));

        // Never credit a negative remainder: fall back to one revenue line.
        if (MoneyMath::isNegative($rest)) {
            return [$default => $net];
        }

        return array_filter([$default => $rest] + $mapped, fn (string $amount) => ! MoneyMath::isZero($amount));
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
