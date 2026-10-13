<?php

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Services\Accounting\Exceptions\InvalidAccountException;
use App\Services\Accounting\FiscalPeriodService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\ReportService;
use Livewire\Livewire;

beforeEach(function () {
    $this->a = accountingVendor();
    $this->b = accountingVendor();
    $this->userA = accountingUser($this->a);
    $this->entryB = postEntry($this->b, '2026-03-01', '1000', '4000', '777.00', 'Vendor B secret');
});

test('vendor A cannot open vendor B accounts or entries', function () {
    $this->actingAs($this->userA)
        ->get(route('vendor.accounting.accounts.edit', accountId($this->b, '1000')))->assertNotFound();

    $this->actingAs($this->userA)
        ->get(route('vendor.accounting.journal.show', $this->entryB))->assertNotFound();
});

test('vendor A lists only their own accounts, entries and periods', function () {
    app(FiscalPeriodService::class)->createQuarters($this->b->id, 2026);

    $this->actingAs($this->userA);
    expect(ChartOfAccount::pluck('vendor_id')->unique()->all())->toBe([$this->a->id])
        ->and(FiscalPeriod::count())->toBe(0);

    Livewire::test('vendor.accounting.journal.index')
        ->set('from', '2026-01-01')->set('to', '2026-12-31')
        ->assertDontSee('Vendor B secret');
});

test('vendor A reports ignore vendor B', function () {
    $tb = app(ReportService::class)->trialBalance($this->a->id, now()->setDate(2026, 12, 31));

    expect($tb['rows'])->toBe([]);

    Livewire::actingAs($this->userA)->test('vendor.accounting.reports.general-ledger')
        ->set('account', accountId($this->b, '1000'))
        ->assertSet('report', null);
});

test('vendor A cannot post to vendor B accounts, even by id', function () {
    Livewire::actingAs($this->userA)->test('vendor.accounting.journal.form')
        ->set('memo', 'sneaky')
        ->set('lines', [
            ['account_id' => (string) accountId($this->b, '1000'), 'description' => '', 'debit' => '10', 'credit' => ''],
            ['account_id' => (string) accountId($this->a, '4000'), 'description' => '', 'debit' => '', 'credit' => '10'],
        ])
        ->call('post')
        ->assertHasErrors('lines');

    expect(fn () => app(LedgerService::class)->post([
        ['account_id' => accountId($this->b, '1000'), 'debit' => '10'],
        ['account_code' => '4000', 'credit' => '10'],
    ], ['vendor_id' => $this->a->id, 'date' => now()]))->toThrow(InvalidAccountException::class);
});
