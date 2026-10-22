<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reading;
use App\Models\Tariff;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoicePdfService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateInvoiceFromReading
{
    public function __construct(
        protected InvoiceNumberGenerator $numbers,
        protected InvoicePdfService $pdf,
    ) {}

    /**
     * Build an invoice from a reading + tariff, then generate the PDF.
     *
     * @param  float  $taxRate  e.g. 16 for 16% VAT
     */
    public function handle(Reading $reading, ?Tariff $tariff = null, float $taxRate = 0, int $dueInDays = 14): Invoice
    {
        if (! $reading->isApproved()) {
            throw new RuntimeException('Only approved readings can be invoiced.');
        }

        if ($reading->invoice()->exists()) {
            throw new RuntimeException("Reading #{$reading->id} already has an invoice.");
        }

        $reading->loadMissing('meter.client', 'vendor');

        $tariff ??= Tariff::ensureDefaultFor($reading->vendor_id);

        $consumption = (float) $reading->consumption;

        if ($consumption <= 0) {
            throw new RuntimeException('Reading has no consumption to bill for.');
        }

        $subtotal = $tariff->chargeFor($consumption);
        $tax = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $tax, 2);

        $issuedAt = static::issueDateFor($reading);

        $invoice = DB::transaction(function () use ($reading, $tariff, $consumption, $subtotal, $tax, $total, $taxRate, $dueInDays, $issuedAt) {
            $invoice = Invoice::create([
                'client_id' => $reading->meter->client_id,
                'unit_id' => $reading->meter->unit_id,
                'reading_id' => $reading->id,
                'number' => $this->numbers->next($reading->vendor_id),
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->copy()->addDays($dueInDays),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => InvoiceStatus::Draft,
                'notes' => $taxRate > 0 ? "Tax rate: {$taxRate}%" : null,
            ]);

            $this->createLineItems($invoice, $tariff, $consumption);

            return $invoice;
        });

        // PDF outside the transaction — I/O should not block the DB lock
        $this->pdf->store($invoice);

        return $invoice->fresh(['items', 'client']);
    }

    /**
     * When the invoice is dated. A reading from the current month is billed
     * today; a reading backfilled for an earlier month is billed on its own
     * reading date, so the invoice, its due date, payments and the ledger
     * all fall in the month the water was actually used.
     */
    public static function issueDateFor(Reading $reading): CarbonInterface
    {
        return $reading->read_at->isSameMonth(now())
            ? now()
            : $reading->read_at->copy();
    }

    protected function createLineItems(Invoice $invoice, Tariff $tariff, float $consumption): void
    {
        if ($tariff->type === 'flat') {
            $invoice->items()->create([
                'category' => InvoiceItem::WATER,
                'description' => "Water consumption — {$consumption} m³ @ "
                    .number_format((float) $tariff->rate, 2).' / m³',
                'quantity' => $consumption,
                'unit_price' => $tariff->rate,
                'total' => round($consumption * (float) $tariff->rate, 2),
            ]);

            return;
        }

        // Tiered — one line per band
        $last = 0;
        foreach (($tariff->tiers ?? []) as $tier) {
            $upTo = $tier['up_to'] ?? null;
            $band = $upTo === null
                ? max(0, $consumption - $last)
                : max(0, min($upTo, $consumption) - $last);

            if ($band <= 0) {
                break;
            }

            $invoice->items()->create([
                'category' => InvoiceItem::WATER,
                'description' => "Water — band of {$band} m³ @ "
                    .number_format((float) $tier['rate'], 2).' / m³',
                'quantity' => $band,
                'unit_price' => $tier['rate'],
                'total' => round($band * (float) $tier['rate'], 2),
            ]);

            $last = $upTo ?? $last;
            if ($upTo === null || $last >= $consumption) {
                break;
            }
        }
    }
}
