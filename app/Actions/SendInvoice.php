<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\SmsMessage;
use App\Services\InvoicePdfService;
use App\Services\Sms\InvoiceSms;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sends an invoice to its client. A draft is issued first (marked sent, which
 * posts it to the ledger and applies any M-Pesa credit) and its PDF rebuilt;
 * an invoice that is already sent or overdue is simply texted again.
 *
 * Accounting exceptions (e.g. a closed fiscal period) bubble up untouched so
 * the caller can show them; the draft then stays a draft.
 */
class SendInvoice
{
    public function __construct(
        protected InvoicePdfService $pdf,
        protected InvoiceSms $sms,
    ) {}

    /**
     * @return SmsMessage|null The SMS sent, or null when M-Pesa credit already settled a freshly issued invoice.
     */
    public function handle(Invoice $invoice): ?SmsMessage
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            // The status change and its ledger entry succeed or fail together.
            DB::transaction(fn () => $invoice->update([
                'status' => InvoiceStatus::Sent,
                'issued_at' => $invoice->issued_at ?? now(),
            ]));

            $this->pdf->regenerate($invoice);
            $invoice->refresh();

            return $invoice->status->isPayable() ? $this->sms->send($invoice) : null;
        }

        if (! $invoice->status->isPayable()) {
            throw new RuntimeException("A {$invoice->status->label()} invoice cannot be sent.");
        }

        return $this->sms->send($invoice);
    }

    /**
     * What to tell the user after sending.
     *
     * @return array{variant: string, text: string}
     */
    public static function outcome(?SmsMessage $sms): array
    {
        return match ($sms?->status) {
            'sent' => ['variant' => 'success', 'text' => 'Invoice sent to the client by SMS.'],
            'logged' => ['variant' => 'warning', 'text' => 'The SMS was only logged, not delivered: sending is off or no SMS provider is set up. Turn on "Send SMS" under Settings → SMS.'],
            'failed' => ['variant' => 'danger', 'text' => 'Invoice marked as sent, but the SMS failed: '.$sms->error],
            default => ['variant' => 'success', 'text' => 'Invoice issued. Existing M-Pesa credit already settled it, so no SMS was needed.'],
        };
    }
}
