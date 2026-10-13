<?php

namespace App\Services\Accounting\Postings;

use App\Services\Accounting\PostableEvent;
use App\Support\Money\MoneyMath;

/** Shared helpers for posting rules. */
abstract class Posting implements PostableEvent
{
    /** Account code for a logical key in config/accounting.php. */
    protected static function code(string $key): string
    {
        return (string) config("accounting.accounts.{$key}");
    }

    /** @return array<string, mixed> */
    protected static function dr(string $code, string $amount, array $extra = []): array
    {
        return ['account_code' => $code, 'debit' => MoneyMath::str($amount), 'credit' => '0.00'] + $extra;
    }

    /** @return array<string, mixed> */
    protected static function cr(string $code, string $amount, array $extra = []): array
    {
        return ['account_code' => $code, 'debit' => '0.00', 'credit' => MoneyMath::str($amount)] + $extra;
    }
}
