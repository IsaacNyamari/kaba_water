<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\Payment;
use App\Services\InvoicePdfService;
use App\Support\Money\MoneyMath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applies money a customer paid to their unpaid invoices: a chosen invoice
 * first, then the oldest first. Each invoice settled becomes one Payment
 * row, so the ledger, PDFs and statements work exactly as for a payment
 * recorded by hand.
 */
class PaymentAllocator
{
    public function __construct(protected InvoicePdfService $pdf) {}

    /**
     * Apply up to $amount for $client. Returns the amount actually applied
     * (less than $amount when the client owes less).
     *
     * @param  bool  $fromDeposit  true when the money is already held as a customer deposit (suspense / credit)
     */
    public function allocate(
        Client $client,
        string $amount,
        ?MpesaTransaction $transaction = null,
        ?Invoice $preferred = null,
        bool $fromDeposit = false,
        ?int $recordedBy = null,
    ): string {
        $remaining = MoneyMath::str($amount);
        $applied = '0.00';

        foreach ($this->unpaidInvoices($client, $preferred) as $invoice) {
            if (! MoneyMath::isPositive($remaining)) {
                break;
            }

            $balance = MoneyMath::sub((string) $invoice->total, (string) $invoice->amount_paid);
            if (! MoneyMath::isPositive($balance)) {
                continue;
            }

            $portion = MoneyMath::compare($remaining, $balance) < 0 ? $remaining : $balance;

            DB::transaction(function () use ($invoice, $client, $portion, $transaction, $fromDeposit, $recordedBy) {
                Payment::create([
                    'vendor_id' => $client->vendor_id,
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'amount' => $portion,
                    'method' => PaymentMethod::Mpesa->value,
                    'reference' => $transaction?->receipt,
                    'mpesa_transaction_id' => $transaction?->id,
                    'from_customer_deposit' => $fromDeposit,
                    'paid_at' => $transaction?->transacted_at ?? now(),
                    'recorded_by' => $recordedBy,
                ]);

                $invoice->syncPayments();
            });

            rescue(fn () => $this->pdf->regenerate($invoice->fresh()), report: false);

            $remaining = MoneyMath::sub($remaining, $portion);
            $applied = MoneyMath::add($applied, $portion);
        }

        return $applied;
    }

    /** What the client still owes on sent / overdue invoices. */
    public function outstanding(Client $client): string
    {
        return MoneyMath::sumColumn(
            $this->unpaidInvoices($client)->map(fn (Invoice $i) => ['b' => MoneyMath::sub((string) $i->total, (string) $i->amount_paid)]),
            'b',
        );
    }

    /** @return Collection<int, Invoice> */
    protected function unpaidInvoices(Client $client, ?Invoice $preferred = null)
    {
        $invoices = Invoice::withoutGlobalScope('vendor')
            ->where('vendor_id', $client->vendor_id)
            ->where('client_id', $client->id)
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        if ($preferred && $invoices->contains('id', $preferred->id)) {
            $invoices = $invoices->sortBy(fn (Invoice $i) => $i->id === $preferred->id ? 0 : 1)->values();
        }

        return $invoices;
    }
}
