<?php

namespace App\Services\Prepaid;

use App\Models\Meter;

/**
 * Test mode: issues a random 20-digit number so the whole M-Pesa → token →
 * SMS flow can be tried before a real vending system is connected. These
 * tokens do NOT load on a real meter.
 */
class SimulatorTokenVendor implements TokenVendor
{
    public function vend(Meter $meter, string $amount, string $units, string $reference): TokenResult
    {
        $token = '';
        for ($i = 0; $i < 20; $i++) {
            $token .= random_int(0, 9);
        }

        return new TokenResult($token, $units, 'SIM-'.$reference);
    }
}
