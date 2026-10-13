<?php

use App\Models\JournalEntry;
use App\Services\Accounting\Exceptions\UnbalancedBalanceSheetException;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hand-crafted ledger (all in March 2026 unless noted):
 *   Feb 28  Owner capital         Dr 1000 10,000.00 / Cr 3000 10,000.00
 *   Mar 01  Buy equipment         Dr 1500  4,000.00 / Cr 1000  4,000.00
 *   Mar 05  Invoice (net 1,000 + VAT 160)  Dr 1100 1,160 / Cr 4000 1,000 / Cr 2100 160
 *   Mar 10  Payment received      Dr 1000    700.00 / Cr 1100    700.00
 *   Mar 15  Water bought on credit Dr 5000   300.00 / Cr 2000    300.00
 *   Mar 20  Salaries              Dr 5100    250.50 / Cr 1000    250.50
 *   Mar 31  Depreciation          Dr 5400    100.00 / Cr 1510    100.00
 */
beforeEach(function () {
    $this->vendor = $v = accountingVendor();
    $this->reports = app(ReportService::class);

    postEntry($v, '2026-02-28', '1000', '3000', '10000.00', 'Capital');
    postEntry($v, '2026-03-01', '1500', '1000', '4000.00', 'Equipment');
    app(LedgerService::class)->post([
        ['account_code' => '1100', 'debit' => '1160.00'],
        ['account_code' => '4000', 'credit' => '1000.00'],
        ['account_code' => '2100', 'credit' => '160.00'],
    ], ['vendor_id' => $v->id, 'date' => '2026-03-05', 'memo' => 'Invoice']);
    postEntry($v, '2026-03-10', '1000', '1100', '700.00', 'Payment');
    postEntry($v, '2026-03-15', '5000', '2000', '300.00', 'Water');
    postEntry($v, '2026-03-20', '5100', '1000', '250.50', 'Salaries');
    postEntry($v, '2026-03-31', '5400', '1510', '100.00', 'Depreciation');

    $this->from = CarbonImmutable::parse('2026-03-01');
    $this->to = CarbonImmutable::parse('2026-03-31');
});

test('trial balance has exact balances and equal totals', function () {
    $tb = $this->reports->trialBalance($this->vendor->id, $this->to);
    $row = fn ($code) => collect($tb['rows'])->firstWhere('code', $code);

    expect($row('1000')['debit'])->toBe('6449.50')
        ->and($row('1100')['debit'])->toBe('460.00')
        ->and($row('1510')['credit'])->toBe('100.00')
        ->and($row('3000')['credit'])->toBe('10000.00')
        ->and($tb['total_debit'])->toBe('11560.00')
        ->and($tb['total_credit'])->toBe('11560.00')
        ->and($tb['balanced'])->toBeTrue();
});

test('income statement computes net income for the period', function () {
    $is = $this->reports->incomeStatement($this->vendor->id, $this->from, $this->to);

    expect($is['total_revenue'])->toBe('1000.00')
        ->and($is['total_expenses'])->toBe('650.50')
        ->and($is['net_income'])->toBe('349.50');

    // February had no income or expense.
    $feb = $this->reports->incomeStatement($this->vendor->id, CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-02-28'));
    expect($feb['net_income'])->toBe('0.00');
});

test('balance sheet satisfies assets = liabilities + equity', function () {
    $bs = $this->reports->balanceSheet($this->vendor->id, $this->to);

    // Assets: cash 6,449.50 + AR 460 + equipment 4,000 − acc. dep. 100 = 10,809.50
    // Liabilities: AP 300 + VAT 160 = 460; Equity: 10,000 + earnings 349.50
    expect($bs['total_assets'])->toBe('10809.50')
        ->and($bs['total_liabilities'])->toBe('460.00')
        ->and($bs['current_earnings'])->toBe('349.50')
        ->and($bs['total_equity'])->toBe('10349.50')
        ->and($bs['total_liabilities_and_equity'])->toBe($bs['total_assets']);
});

test('balance sheet throws when the ledger is corrupted', function () {
    // Simulate corruption that bypasses LedgerService (a lone debit line).
    $entry = JournalEntry::withoutGlobalScope('vendor')->create(['vendor_id' => $this->vendor->id, 'date' => '2026-03-31', 'memo' => 'bad']);
    DB::table('journal_lines')->insert(['journal_entry_id' => $entry->id, 'account_id' => accountId($this->vendor, '1000'), 'debit' => '5.00', 'credit' => '0', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('journal_entries')->where('id', $entry->id)->update(['posted_at' => now()]);

    $this->reports->balanceSheet($this->vendor->id, $this->to);
})->throws(UnbalancedBalanceSheetException::class);

test('cash flow ties to the change in cash', function () {
    $cf = $this->reports->cashFlow($this->vendor->id, $this->from, $this->to);

    // Operating: NI 349.50 + dep 100 − ΔAR 460 + ΔAP 300 + ΔVAT 160 = 449.50
    expect($cf['net_income'])->toBe('349.50')
        ->and($cf['operating'])->toBe('449.50')
        ->and($cf['investing_total'])->toBe('-4000.00')
        ->and($cf['financing_total'])->toBe('0.00')
        ->and($cf['opening_cash'])->toBe('10000.00')
        ->and($cf['closing_cash'])->toBe('6449.50')
        ->and($cf['net_change'])->toBe('-3550.50')
        ->and($cf['ties'])->toBeTrue();
});

test('general ledger running balance matches the lines', function () {
    $gl = $this->reports->generalLedger($this->vendor->id, accountId($this->vendor, '1000'), $this->from, $this->to);

    expect($gl['opening'])->toBe('10000.00')
        ->and(array_column($gl['rows'], 'balance'))->toBe(['6000.00', '6700.00', '6449.50'])
        ->and($gl['closing'])->toBe('6449.50');
});

test('reports only include posted entries of the vendor', function () {
    $other = accountingVendor();
    postEntry($other, '2026-03-05', '1000', '4000', '999999.00');
    JournalEntry::withoutGlobalScope('vendor')->create(['vendor_id' => $this->vendor->id, 'date' => '2026-03-05', 'memo' => 'draft']);

    $is = $this->reports->incomeStatement($this->vendor->id, $this->from, $this->to);
    expect($is['total_revenue'])->toBe('1000.00');
});
