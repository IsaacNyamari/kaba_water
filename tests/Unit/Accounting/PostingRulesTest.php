<?php

use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\SalaryPaid;
use App\Services\Accounting\Postings\WaterPurchase;

test('water bought on credit goes to payables, salaries to cash', function () {
    $vendor = accountingVendor();
    $ledger = app(LedgerService::class);

    $water = $ledger->postEvent(new WaterPurchase($vendor->id, '900.00', '2026-04-01', 'Bulk water', onCredit: true));
    $salary = $ledger->postEvent(new SalaryPaid($vendor->id, '400.00', '2026-04-30', 'April wages'));

    $code = fn ($entry, $side) => $entry->lines->first(fn ($l) => (string) $l->{$side} !== '0.00')->account->code;
    expect($code($water, 'debit'))->toBe('5000')->and($code($water, 'credit'))->toBe('2000')
        ->and($code($salary, 'debit'))->toBe('5100')->and($code($salary, 'credit'))->toBe('1000');
});
