<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Accounting\FiscalPeriodService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\CreditNoteIssued;
use App\Services\Accounting\Postings\InvoiceIssued;

/**
 * Auto-posts invoices to the ledger.
 *
 * Posting happens when an invoice is ISSUED (leaves draft), not when the
 * draft row is created: drafts can still be edited or deleted and are not a
 * receivable yet. Voiding an issued invoice posts the credit note.
 */
class InvoiceObserver
{
    public function __construct(
        protected LedgerService $ledger,
        protected FiscalPeriodService $periods,
    ) {}

    /** Refuse a status change whose posting would land in a closed period. */
    public function saving(Invoice $invoice): void
    {
        if (! $invoice->isDirty('status')) {
            return;
        }

        if ($this->isIssued($invoice) && ! $this->wasIssued($invoice)) {
            $this->periods->assertOpen($invoice->vendor_id, $invoice->issued_at ?? now());
        }

        if ($invoice->status === InvoiceStatus::Void && $this->wasIssued($invoice)) {
            $this->periods->assertOpen($invoice->vendor_id, now());
        }
    }

    public function created(Invoice $invoice): void
    {
        if ($this->isIssued($invoice)) {
            $this->ledger->postEvent(new InvoiceIssued($invoice));
        }
    }

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status')) {
            return;
        }

        if ($this->isIssued($invoice)) {
            $this->ledger->postEvent(new InvoiceIssued($invoice)); // idempotent
        }

        if ($invoice->status === InvoiceStatus::Void) {
            $issued = $this->ledger->findPosted((new InvoiceIssued($invoice))->meta());
            if ($issued) {
                $this->ledger->postEvent(new CreditNoteIssued($invoice));
            }
        }
    }

    /** Deleting an issued invoice reverses its ledger entry. */
    public function deleting(Invoice $invoice): void
    {
        $issued = $this->ledger->findPosted((new InvoiceIssued($invoice))->meta());

        if ($issued) {
            $this->ledger->reverse($issued, "Invoice {$invoice->number} deleted");
        }
    }

    /** Sent, overdue, paid and written-off invoices are (or were) receivables. */
    protected function isIssued(Invoice $invoice): bool
    {
        return in_array($invoice->status, [
            InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Paid, InvoiceStatus::WrittenOff,
        ], true);
    }

    protected function wasIssued(Invoice $invoice): bool
    {
        $original = $invoice->getOriginal('status');
        $original = $original instanceof InvoiceStatus ? $original : InvoiceStatus::tryFrom((string) $original);

        return in_array($original, [
            InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Paid, InvoiceStatus::WrittenOff,
        ], true);
    }
}
