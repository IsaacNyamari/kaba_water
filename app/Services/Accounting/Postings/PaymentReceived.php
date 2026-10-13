<?php

namespace App\Services\Accounting\Postings;

use App\Models\Payment;

/**
 * A customer pays.
 *   Dr 1000 Cash / Bank (per payment method, config/accounting.php)
 *   Cr 1100 Accounts Receivable
 */
class PaymentReceived extends Posting
{
    public const EVENT = 'payment.received';

    public function __construct(public Payment $payment) {}

    public function lines(): array
    {
        $p = $this->payment;
        $method = $p->method?->value ?? (string) $p->method;
        $cashCode = (string) config("accounting.payment_method_accounts.{$method}", self::code('cash'));
        $tags = ['client_id' => $p->client_id, 'description' => 'Payment'.($p->reference ? " {$p->reference}" : '')];

        return [
            self::dr($cashCode, (string) $p->amount, $tags),
            self::cr(self::code('receivables'), (string) $p->amount, $tags),
        ];
    }

    public function meta(): array
    {
        $p = $this->payment;

        return [
            'vendor_id' => $p->vendor_id,
            'date' => $p->paid_at ?? now(),
            'reference' => $p->reference ?: ($p->invoice?->number),
            'memo' => 'Payment received'.($p->invoice ? " for {$p->invoice->number}" : ''),
            'source_type' => Payment::class,
            'source_id' => $p->id,
            'source_event' => self::EVENT,
            'created_by' => $p->recorded_by,
        ];
    }
}
