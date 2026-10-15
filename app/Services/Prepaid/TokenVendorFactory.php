<?php

namespace App\Services\Prepaid;

use App\Models\Integration;

/** Picks the vending driver a vendor configured. */
class TokenVendorFactory
{
    public const DRIVERS = [
        'simulator' => 'Test mode (tokens will not load on real meters)',
        'http' => 'Vending system web API',
    ];

    public function for(Integration $integration): TokenVendor
    {
        return match ($integration->setting('driver')) {
            'http' => new HttpTokenVendor($integration),
            'simulator' => new SimulatorTokenVendor,
            default => throw new TokenVendingException('No token vending system is configured.'),
        };
    }
}
