<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Accounting\Exceptions\InvoiceWrittenOffException;
use App\Services\Accounting\FiscalPeriodService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\PaymentReceived;

/** Payment created → PaymentReceived; payment deleted → reversal. */
class PaymentObserver
{
    public function __construct(
        protected LedgerService $ledger,
        protected FiscalPeriodService $periods,
    ) {}

    public function creating(Payment $payment): void
    {
        $this->assertNotWrittenOff($payment, 'record a payment against');
        $this->periods->assertOpen($payment->vendor_id ?? auth()->user()?->vendor_id, $payment->paid_at ?? now());
    }

    public function created(Payment $payment): void
    {
        $this->ledger->postEvent(new PaymentReceived($payment));
    }

    public function deleting(Payment $payment): void
    {
        $this->assertNotWrittenOff($payment, 'delete a payment of');
        $this->periods->assertOpen($payment->vendor_id, now());
    }

    /**
     * A write-off moves the balance that was unpaid at that moment to bad debt.
     * Changing the payments afterwards would leave receivables disagreeing
     * with the invoice, so it is refused.
     *
     * @throws InvoiceWrittenOffException
     */
    protected function assertNotWrittenOff(Payment $payment, string $action): void
    {
        $invoice = $payment->invoice_id
            ? Invoice::withoutGlobalScope('vendor')->find($payment->invoice_id)
            : null;

        if ($invoice?->status === InvoiceStatus::WrittenOff) {
            throw new InvoiceWrittenOffException("Cannot {$action} invoice {$invoice->number}: it has been written off.");
        }
    }

    public function deleted(Payment $payment): void
    {
        $entry = $this->ledger->findPosted((new PaymentReceived($payment))->meta());

        if ($entry) {
            $this->ledger->reverse($entry, 'Payment deleted');
        }
    }
}
