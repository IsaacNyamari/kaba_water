<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Reading;
use App\Models\Tariff;
use App\Services\InvoicePdfService;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bills a reading the moment it counts: once a reading is approved and the
 * meter has an earlier approved reading to measure from, the units used
 * (current − previous) are invoiced at the active tariff, the invoice is
 * issued (so it posts to the ledger and any M-Pesa credit is applied) and
 * the client gets an SMS with a pay link.
 *
 * Never throws — a reading is always saved; when no invoice can be made the
 * reason is returned so the screen can say why.
 */
class InvoiceReadingAutomatically
{
    public function __construct(
        protected GenerateInvoiceFromReading $generate,
        protected InvoicePdfService $pdf,
        protected SmsService $sms,
    ) {}

    /**
     * @return array{invoice: ?Invoice, reason: ?string}
     */
    public function handle(Reading $reading): array
    {
        $reading->loadMissing('meter.client');
        $skip = fn (string $reason) => ['invoice' => null, 'reason' => $reason];

        if (! $reading->isApproved()) {
            return $skip('The reading is waiting for approval; it will be billed when approved.');
        }

        if ($reading->invoice()->exists()) {
            return ['invoice' => $reading->invoice, 'reason' => null];
        }

        if ($reading->meter?->isPrepaid()) {
            return $skip('Prepaid meters are paid for with tokens, not bills.');
        }

        // The first reading of a meter is its starting point — there is nothing to measure from yet.
        if (Reading::lastApprovedValueFor($reading->meter_id, $reading->read_at, $reading->id) === null) {
            return $skip('This is the first reading of this meter; the next one will be billed.');
        }

        if ((int) $reading->consumption <= 0) {
            return $skip('No water was used since the previous reading, so there is nothing to bill.');
        }

        $tariff = Tariff::ensureDefaultFor($reading->vendor_id);

        try {
            $invoice = $this->generate->handle($reading, $tariff);

            // Issue it: posts to the ledger and applies any M-Pesa credit the client already has.
            DB::transaction(fn () => $invoice->update(['status' => InvoiceStatus::Sent]));
            rescue(fn () => $this->pdf->regenerate($invoice->fresh()), report: false);
        } catch (Throwable $e) {
            report($e);

            return $skip('The invoice could not be created: '.$e->getMessage());
        }

        $invoice->refresh();
        $client = $reading->meter->client;

        if ($client && $invoice->status->isPayable()) {
            $this->sms->send(
                $invoice->vendor_id,
                $client->phone,
                "Dear {$client->name}, your water bill {$invoice->number}: ".number_format((int) $reading->consumption).' m3 used, KES '
                    .number_format($invoice->balance(), 2)." due {$invoice->due_at?->format('d M')}. Pay with M-Pesa: {$invoice->payUrl()}",
                'invoice',
                $client,
            );
        }

        return ['invoice' => $invoice, 'reason' => null];
    }
}
