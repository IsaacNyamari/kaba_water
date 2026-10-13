<?php

namespace App\Actions\Accounting;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\BadDebtWrittenOff;
use App\Support\Money\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Write off an invoice's unpaid balance as bad debt (Dr 5300 / Cr 1100). */
class WriteOffInvoice
{
    public function __construct(protected LedgerService $ledger) {}

    public function handle(Invoice $invoice, ?string $reason = null): Invoice
    {
        if (! $invoice->status->isPayable()) {
            throw ValidationException::withMessages(['invoice' => 'Only sent or overdue invoices can be written off.']);
        }

        $balance = MoneyMath::sub((string) $invoice->total, (string) $invoice->amount_paid);
        if (! MoneyMath::isPositive($balance)) {
            throw ValidationException::withMessages(['invoice' => 'This invoice has no unpaid balance.']);
        }

        return DB::transaction(function () use ($invoice, $balance, $reason) {
            $this->ledger->postEvent(new BadDebtWrittenOff($invoice, $balance));

            $invoice->update([
                'status' => InvoiceStatus::WrittenOff,
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').'Written off '.now()->format('d M Y').($reason ? ": {$reason}" : '')),
            ]);

            return $invoice;
        });
    }
}
