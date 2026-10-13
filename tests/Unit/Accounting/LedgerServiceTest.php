<?php

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Services\Accounting\Exceptions\ClosedPeriodException;
use App\Services\Accounting\Exceptions\InvalidAccountException;
use App\Services\Accounting\Exceptions\UnbalancedEntryException;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\PostableEvent;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->ledger = app(LedgerService::class);
    $this->meta = ['vendor_id' => $this->vendor->id, 'date' => '2026-03-10', 'memo' => 'Test'];
});

test('rejects an unbalanced entry', function () {
    $this->ledger->post([
        ['account_code' => '1000', 'debit' => '100.00'],
        ['account_code' => '4000', 'credit' => '99.99'],
    ], $this->meta);
})->throws(UnbalancedEntryException::class);

test('rejects a single-line entry and an all-zero entry', function () {
    expect(fn () => $this->ledger->post([['account_code' => '1000', 'debit' => '5']], $this->meta))
        ->toThrow(UnbalancedEntryException::class);

    expect(fn () => $this->ledger->post([
        ['account_code' => '1000', 'debit' => '0'], ['account_code' => '4000', 'credit' => '0'],
    ], $this->meta))->toThrow(UnbalancedEntryException::class);
});

test('rejects a line that is both a debit and a credit, or negative', function () {
    expect(fn () => $this->ledger->post([
        ['account_code' => '1000', 'debit' => '10', 'credit' => '10'],
        ['account_code' => '4000', 'credit' => '0'],
    ], $this->meta))->toThrow(UnbalancedEntryException::class);

    expect(fn () => $this->ledger->post([
        ['account_code' => '1000', 'debit' => '-10'], ['account_code' => '4000', 'credit' => '-10'],
    ], $this->meta))->toThrow(UnbalancedEntryException::class);
});

test('rejects dates inside a closed fiscal period', function () {
    FiscalPeriod::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'name' => '2026-Q1',
        'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'is_closed' => true,
    ]);

    postEntry($this->vendor, '2026-03-10', '1000', '4000', '50.00');
})->throws(ClosedPeriodException::class);

test('rejects accounts from another vendor', function () {
    $other = accountingVendor();

    $this->ledger->post([
        ['account_id' => accountId($other, '1000'), 'debit' => '10'],
        ['account_code' => '4000', 'credit' => '10'],
    ], $this->meta);
})->throws(InvalidAccountException::class);

test('rejects inactive accounts', function () {
    ChartOfAccount::withoutGlobalScope('vendor')->whereKey(accountId($this->vendor, '4200'))->update(['is_active' => false]);

    postEntry($this->vendor, '2026-03-10', '1000', '4200', '10.00');
})->throws(InvalidAccountException::class);

test('posts a balanced entry and sets posted_at', function () {
    $entry = postEntry($this->vendor, '2026-03-10', '1000', '4000', '1234.56');

    expect($entry->posted_at)->not->toBeNull()
        ->and($entry->lines)->toHaveCount(2)
        ->and($entry->total_debit)->toBe('1234.56')
        ->and($entry->total_credit)->toBe('1234.56')
        ->and($entry->is_balanced)->toBeTrue()
        ->and($entry->date->toDateString())->toBe('2026-03-10');
});

test('drops zero lines such as a zero tax line', function () {
    $entry = $this->ledger->post([
        ['account_code' => '1100', 'debit' => '100'],
        ['account_code' => '4000', 'credit' => '100'],
        ['account_code' => '2100', 'credit' => '0'],
    ], $this->meta);

    expect($entry->lines)->toHaveCount(2);
});

test('posted entries cannot be edited or deleted', function () {
    $entry = postEntry($this->vendor, '2026-03-10', '1000', '4000', '10.00');

    expect(fn () => $entry->update(['memo' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => $entry->lines->first()->update(['debit' => '20']))->toThrow(LogicException::class);
});

test('reversal creates a linked mirror entry and never deletes the original', function () {
    $entry = postEntry($this->vendor, '2026-03-10', '1000', '4000', '75.00');

    $reversal = $this->ledger->reverse($entry, 'Wrong account');

    $entry->refresh();
    expect($entry->reversed_by_id)->toBe($reversal->id)
        ->and($reversal->reverses_id)->toBe($entry->id)
        ->and(JournalEntry::withoutGlobalScope('vendor')->count())->toBe(2);

    $cash = $reversal->lines->firstWhere('account_id', accountId($this->vendor, '1000'));
    expect((string) $cash->credit)->toBe('75.00')->and((string) $cash->debit)->toBe('0.00');

    expect(fn () => $this->ledger->reverse($entry->fresh(), 'again'))->toThrow(LogicException::class);
});

test('postEvent is idempotent per source and event', function () {
    $event = new class($this->vendor->id) implements PostableEvent
    {
        public function __construct(public int $vendorId) {}

        public function lines(): array
        {
            return [['account_code' => '1000', 'debit' => '5'], ['account_code' => '4000', 'credit' => '5']];
        }

        public function meta(): array
        {
            return ['vendor_id' => $this->vendorId, 'date' => '2026-03-01', 'source_type' => 'X', 'source_id' => 1, 'source_event' => 'x.happened'];
        }
    };

    $a = $this->ledger->postEvent($event);
    $b = $this->ledger->postEvent($event);

    expect($a->id)->toBe($b->id)->and(JournalEntry::withoutGlobalScope('vendor')->count())->toBe(1);
});
