<?php

namespace App\Services\Accounting\Postings;

use App\Models\MpesaTransaction;

/**
 * M-Pesa money that could not be applied when it arrived — no matching
 * account (suspense) or more than the customer owed (credit). The cash is
 * real, but it is not revenue and not a receivable yet: it is held for the
 * customer until it is reconciled.
 *   Dr 1000 Cash / Bank (M-Pesa)
 *   Cr 2200 Customer Deposits
 * Applying it later posts PaymentReceived / PrepaidTokenSold from 2200.
 */
class MpesaDepositReceived extends Posting
{
    public const EVENT = 'mpesa.deposit';

    public function __construct(public MpesaTransaction $transaction, public string $amount) {}

    public function lines(): array
    {
        $t = $this->transaction;
        $cashCode = (string) config('accounting.payment_method_accounts.mpesa', self::code('cash'));
        $tags = ['client_id' => $t->client_id, 'description' => "M-Pesa {$t->receipt} held"];

        return [
            self::dr($cashCode, $this->amount, $tags),
            self::cr(self::code('customer_deposits'), $this->amount, $tags),
        ];
    }

    public function meta(): array
    {
        $t = $this->transaction;

        return [
            'vendor_id' => $t->vendor_id,
            'date' => $t->transacted_at ?? now(),
            'reference' => $t->receipt,
            'memo' => $t->client_id ? 'M-Pesa overpayment held as customer credit' : 'M-Pesa payment held in suspense',
            'source_type' => MpesaTransaction::class,
            'source_id' => $t->id,
            'source_event' => self::EVENT,
            'created_by' => null,
        ];
    }
}
