<?php

namespace App\Services\Accounting\Postings;

use App\Models\PrepaidToken;

/**
 * A prepaid water token is sold. The part of the payment that went to the
 * token is water revenue; any arrears deduction is posted separately as an
 * ordinary PaymentReceived against the old invoices.
 *   Dr 1000 Cash / Bank (M-Pesa) — or 2200 Customer Deposits when paid from held money
 *   Cr 4000 Water Revenue
 */
class PrepaidTokenSold extends Posting
{
    public const EVENT = 'prepaid.token_sold';

    public function __construct(public PrepaidToken $token, public bool $fromDeposit = false) {}

    public function lines(): array
    {
        $t = $this->token;
        $debit = $this->fromDeposit
            ? self::code('customer_deposits')
            : (string) config('accounting.payment_method_accounts.mpesa', self::code('cash'));
        $tags = ['client_id' => $t->client_id, 'meter_id' => $t->meter_id, 'description' => 'Prepaid water token'];

        return [
            self::dr($debit, (string) $t->token_amount, $tags),
            self::cr(self::code('water_revenue'), (string) $t->token_amount, $tags),
        ];
    }

    public function meta(): array
    {
        $t = $this->token;

        return [
            'vendor_id' => $t->vendor_id,
            'date' => $t->created_at ?? now(),
            'reference' => $t->transaction?->receipt,
            'memo' => 'Prepaid token for meter '.$t->meter?->serial,
            'source_type' => PrepaidToken::class,
            'source_id' => $t->id,
            'source_event' => self::EVENT,
            'created_by' => $t->issued_by,
        ];
    }
}
