<?php

use App\Services\Accounting\LedgerService;
use App\Support\Money\MoneyMath;

test('amounts are exact decimals, never floats', function () {
    expect(MoneyMath::add('0.10', '0.20'))->toBe('0.30')
        ->and(MoneyMath::sub('100.00', '33.33'))->toBe('66.67')
        ->and(MoneyMath::add('999999999999.99', '0.01'))->toBe('1000000000000.00');
});

test('floats are rejected', function () {
    MoneyMath::of(0.1 + 0.2);
})->throws(InvalidArgumentException::class);

test('non-numeric strings are rejected', function () {
    MoneyMath::of('12abc');
})->throws(InvalidArgumentException::class);

test('rounding is half-up to two decimals', function () {
    expect(MoneyMath::str('10.005'))->toBe('10.01')
        ->and(MoneyMath::str('10.004'))->toBe('10.00')
        ->and(MoneyMath::multiply('1000.00', '0.16'))->toBe('160.00')
        ->and(MoneyMath::multiply('333.33', '0.16'))->toBe('53.33');
});

test('splitting keeps every cent', function () {
    $parts = MoneyMath::split('100.00', 3);

    expect($parts)->toBe(['33.34', '33.33', '33.33'])
        ->and(MoneyMath::add(...$parts))->toBe('100.00');
});

test('posted split lines still balance', function () {
    $vendor = accountingVendor();
    [$a, $b, $c] = MoneyMath::split('100.00', 3);

    $entry = app(LedgerService::class)->post([
        ['account_code' => '1100', 'debit' => '100.00'],
        ['account_code' => '4000', 'credit' => $a],
        ['account_code' => '4100', 'credit' => $b],
        ['account_code' => '4200', 'credit' => $c],
    ], ['vendor_id' => $vendor->id, 'date' => now()]);

    expect($entry->is_balanced)->toBeTrue();
});

test('formatting', function () {
    expect(MoneyMath::format('1234567.5'))->toBe('1,234,567.50')
        ->and(MoneyMath::format('-42'))->toBe('-42.00');
});
