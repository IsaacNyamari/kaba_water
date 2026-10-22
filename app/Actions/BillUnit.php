<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Unit;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoicePdfService;
use App\Support\Money\MoneyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bills a house (unit) for one month: rent and service charge, plus any
 * extra lines, addressed to the house number and its tenant. The invoice is
 * created as a draft; SendInvoice issues it (posting it to the ledger) and
 * texts the tenant.
 *
 * A house gets at most one live bill per month; voiding that bill frees the
 * month to be billed again.
 */
class BillUnit
{
    public function __construct(
        protected InvoiceNumberGenerator $numbers,
        protected InvoicePdfService $pdf,
    ) {}

    /**
     * @param  string  $period  Month billed, YYYY-MM
     * @param  float  $taxRate  e.g. 16 for 16% VAT
     * @param  list<array{description: string, amount: string|float}>  $extraItems
     */
    public function handle(Unit $unit, string $period, float $taxRate = 0, int $dueInDays = 7, array $extraItems = []): Invoice
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new RuntimeException("Billing month must look like 2026-10, got \"{$period}\".");
        }

        if (! $unit->isOccupied()) {
            throw new RuntimeException("House {$unit->house_number} has no tenant to bill.");
        }

        if ($unit->isBilledFor($period)) {
            throw new RuntimeException("House {$unit->house_number} is already billed for ".self::periodLabel($period).'.');
        }

        $unit->loadMissing('property');
        $month = self::periodLabel($period);

        $lines = array_values(array_filter([
            ['category' => InvoiceItem::RENT, 'description' => "Rent — {$month}", 'amount' => (string) $unit->rent],
            ['category' => InvoiceItem::SERVICE_CHARGE, 'description' => "Service charge — {$month}", 'amount' => (string) $unit->service_charge],
            ...array_map(fn (array $item) => [
                'category' => InvoiceItem::OTHER,
                'description' => trim((string) $item['description']),
                'amount' => (string) $item['amount'],
            ], $extraItems),
        ], fn (array $line) => MoneyMath::isPositive($line['amount'])));

        if ($lines === []) {
            throw new RuntimeException("House {$unit->house_number} has no rent or charges to bill.");
        }

        $subtotal = MoneyMath::sumColumn($lines, 'amount');
        $tax = MoneyMath::multiply($subtotal, (string) ($taxRate / 100));
        $total = MoneyMath::add($subtotal, $tax);
        $issuedAt = self::issueDateFor($period);

        $invoice = DB::transaction(function () use ($unit, $period, $lines, $subtotal, $tax, $total, $taxRate, $dueInDays, $issuedAt) {
            $invoice = Invoice::create([
                'vendor_id' => $unit->vendor_id,
                'client_id' => $unit->client_id,
                'unit_id' => $unit->id,
                'billing_period' => $period,
                'number' => $this->numbers->next($unit->vendor_id),
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->addDays($dueInDays),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => InvoiceStatus::Draft,
                'notes' => trim("House {$unit->fullLabel()}".($taxRate > 0 ? " · Tax rate: {$taxRate}%" : '')),
            ]);

            foreach ($lines as $line) {
                $invoice->items()->create([
                    'category' => $line['category'],
                    'description' => $line['description'],
                    'quantity' => 1,
                    'unit_price' => $line['amount'],
                    'total' => MoneyMath::str($line['amount']),
                ]);
            }

            return $invoice;
        });

        $this->pdf->store($invoice);

        return $invoice->fresh(['items', 'client', 'unit.property']);
    }

    /**
     * The current month is billed today; a past or future month is dated the
     * 1st of that month, so the bill and its ledger entry land in the month
     * the rent is for.
     */
    public static function issueDateFor(string $period): CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $period);

        return $start->isSameMonth(now()) ? CarbonImmutable::now() : $start;
    }

    /** "2026-10" → "October 2026" */
    public static function periodLabel(string $period): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period)->format('F Y');
    }
}
