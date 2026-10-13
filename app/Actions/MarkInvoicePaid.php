<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\DB;

class MarkInvoicePaid
{
    public function __construct(
        protected InvoicePdfService $pdf,
    ) {}

    /**
     * Mark an invoice as paid and record a matching payment row.
     *
     * @param  PaymentMethod  $method     How the payment was received.
     * @param  string|null    $reference  Optional M-Pesa code / cheque no / note.
     * @param  float|null     $amount     Defaults to the invoice's outstanding balance.
     */
    public function handle(
        Invoice $invoice,
        PaymentMethod $method = PaymentMethod::Other,
        ?string $reference = null,
        ?float $amount = null,
    ): Payment {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new \RuntimeException("Invoice {$invoice->number} is already marked as paid.");
        }

        if ($invoice->status === InvoiceStatus::Void) {
            throw new \RuntimeException("Cannot mark a voided invoice as paid.");
        }

        $balance = $invoice->balance();

        if ($balance <= 0) {
            throw new \RuntimeException("Invoice {$invoice->number} has no outstanding balance.");
        }

        $amount ??= $balance;

        if ($amount > $balance + 0.001) {
            throw new \RuntimeException(
                'Payment amount (' . number_format($amount, 2) . ') exceeds the outstanding balance ('
                    . number_format($balance, 2) . ').'
            );
        }

        $payment = DB::transaction(function () use ($invoice, $method, $reference, $amount) {
            $payment = Payment::create([
                'invoice_id'  => $invoice->id,
                'client_id'   => $invoice->client_id,
                'amount'      => $amount,
                'method'      => $method,
                'reference'   => $reference,
                'paid_at'     => now(),
                'recorded_by' => auth()->id(),
            ]);

            $totalPaid = $invoice->payments()->sum('amount');

            $invoice->update([
                'amount_paid' => $totalPaid,
                'status'      => $totalPaid + 0.001 >= (float) $invoice->total
                    ? InvoiceStatus::Paid
                    : $invoice->status,
            ]);

            return $payment;
        });

        // Regenerate the PDF outside the transaction so I/O doesn't hold the row lock.
        $this->pdf->regenerate($invoice);

        return $payment;
    }
}
