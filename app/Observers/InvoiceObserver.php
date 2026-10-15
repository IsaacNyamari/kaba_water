<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Accounting\FiscalPeriodService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\CreditNoteIssued;
use App\Services\Accounting\Postings\InvoiceIssued;
use App\Services\Mpesa\MpesaReconciler;
use Illuminate\Support\Facades\DB;

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

        // A newly sent bill is settled first from any M-Pesa credit the client already has with us.
        if ($invoice->status === InvoiceStatus::Sent && $invoice->getOriginal('status') === InvoiceStatus::Draft) {
            DB::afterCommit(fn () => rescue(fn () => app(MpesaReconciler::class)->applyCredits($invoice->client)));
        }

        if ($invoice->status === InvoiceStatus::Void) {
            $issued = $this->ledger->findPosted((new InvoiceIssued($invoice))->meta());
            if ($issued) {
                $this->ledger->postEvent(new CreditNoteIssued($invoice));
            }
        }
    }

    /**
     * Deleting an invoice takes everything it still has on the books back off:
     * the issue entry and, if it was written off, the bad-debt entry too.
     * A voided invoice is already netted to zero by its credit note, so that
     * pair is left alone rather than reversed a second time.
     */
    public function deleting(Invoice $invoice): void
    {
        $live = JournalEntry::withoutGlobalScope('vendor')
            ->where('vendor_id', $invoice->vendor_id)
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->whereNotNull('posted_at')
            ->whereNull('reversed_by_id')
            ->whereNull('reverses_id')
            ->orderBy('id')
            ->get();

        if ($live->contains('source_event', CreditNoteIssued::EVENT)) {
            $live = $live->reject(fn (JournalEntry $e) => in_array($e->source_event, [InvoiceIssued::EVENT, CreditNoteIssued::EVENT], true));
        }

        foreach ($live as $entry) {
            $this->ledger->reverse($entry, "Invoice {$invoice->number} deleted");
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
