<?php

use App\Support\Money\MoneyMath;
use Livewire\Livewire;

test('running balance matches the sum of lines', function () {
    $vendor = accountingVendor();
    $user = accountingUser($vendor);

    postEntry($vendor, '2026-01-05', '1000', '3000', '1000.00');
    postEntry($vendor, '2026-02-05', '1000', '4000', '250.25');
    postEntry($vendor, '2026-02-10', '5100', '1000', '100.10');
    postEntry($vendor, '2026-03-01', '1000', '4100', '49.85');

    $c = Livewire::actingAs($user)->test('vendor.accounting.reports.general-ledger')
        ->set('account', accountId($vendor, '1000'))
        ->set('from', '2026-02-01')->set('to', '2026-12-31');

    $r = $c->get('report');
    $expected = MoneyMath::add($r['opening'], MoneyMath::sub($r['total_debit'], $r['total_credit']));

    expect($r['opening'])->toBe('1000.00')
        ->and($r['rows'])->toHaveCount(3)
        ->and(end($r['rows'])['balance'])->toBe($expected)
        ->and($r['closing'])->toBe('1200.00');

    $c->call('export')->assertFileDownloaded('general-ledger-1000.xlsx');
});
