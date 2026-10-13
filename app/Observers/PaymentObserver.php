<?php

namespace App\Observers;

use App\Models\Payment;
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
        $this->periods->assertOpen($payment->vendor_id ?? auth()->user()?->vendor_id, $payment->paid_at ?? now());
    }

    public function created(Payment $payment): void
    {
        $this->ledger->postEvent(new PaymentReceived($payment));
    }

    public function deleting(Payment $payment): void
    {
        $this->periods->assertOpen($payment->vendor_id, now());
    }

    public function deleted(Payment $payment): void
    {
        $entry = $this->ledger->findPosted((new PaymentReceived($payment))->meta());

        if ($entry) {
            $this->ledger->reverse($entry, 'Payment deleted');
        }
    }
}
