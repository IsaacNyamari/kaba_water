<?php

namespace App\Services\Prepaid;

use App\Models\Meter;

/**
 * A system that can issue STS tokens for a meter.
 *
 * STS tokens are encrypted with keys held by an STS-certified vending
 * system (the meter maker's or the utility's), so this app never computes
 * tokens itself — it asks the vending system for one and passes it on.
 * Add a class per vending system and register it in TokenVendorFactory.
 */
interface TokenVendor
{
    /**
     * @param  string  $amount  money value of the token (after any arrears deduction)
     * @param  string  $units  water units (m³) the amount buys at the vendor's tariff
     * @param  string  $reference  unique reference for this sale (used for idempotency on the vending side)
     *
     * @throws TokenVendingException when no token could be issued
     */
    public function vend(Meter $meter, string $amount, string $units, string $reference): TokenResult;
}
