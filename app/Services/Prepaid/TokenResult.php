<?php

namespace App\Services\Prepaid;

/** What a vending system returned: the token, and the units it says it credited (if it says). */
final class TokenResult
{
    public function __construct(
        public readonly string $token,
        public readonly ?string $units = null,
        public readonly ?string $reference = null,
    ) {}
}
