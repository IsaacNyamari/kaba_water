<?php

use App\Models\JournalEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor();
});

test('every accounting page renders for the owner', function () {
    $owner = accountingUser($this->vendor);
    postEntry($this->vendor, now()->toDateString(), '1000', '3000', '10.00');

    foreach ([
        'vendor.accounting.dashboard',
        'vendor.accounting.accounts.index', 'vendor.accounting.accounts.create',
        'vendor.accounting.journal.index', 'vendor.accounting.journal.create',
        'vendor.accounting.periods.index', 'vendor.accounting.opening-balances',
        'vendor.accounting.reports.trial-balance', 'vendor.accounting.reports.income-statement',
        'vendor.accounting.reports.balance-sheet', 'vendor.accounting.reports.cash-flow',
        'vendor.accounting.reports.general-ledger',
    ] as $route) {
        $this->actingAs($owner)->get(route($route))->assertOk();
    }

    $this->actingAs($owner)->get(route('vendor.accounting.journal.show', JournalEntry::first()))->assertOk();
});

test('accountants get accounting but not operations', function () {
    $accountant = accountingUser($this->vendor, 'accountant');

    $this->actingAs($accountant)->get(route('vendor.accounting.journal.create'))->assertOk();
    $this->actingAs($accountant)->get(route('vendor.clients.index'))->assertForbidden();
    expect($accountant->homeRoute())->toBe(route('vendor.accounting.dashboard'));
});

test('staff can view but not post', function () {
    $staff = accountingUser($this->vendor, 'vendor_staff');

    $this->actingAs($staff)->get(route('vendor.accounting.reports.trial-balance'))->assertOk();
    $this->actingAs($staff)->get(route('vendor.accounting.journal.create'))->assertForbidden();
});

test('clients and outsiders are kept out', function () {
    $client = accountingUser($this->vendor, 'client');
    $nobody = User::factory()->create();

    $this->actingAs($client)->get(route('vendor.accounting.journal.index'))->assertForbidden();
    $this->actingAs($nobody)->get(route('vendor.accounting.journal.index'))->assertForbidden();
});

test('manual entries must balance and then post', function () {
    $owner = accountingUser($this->vendor);
    $cash = (string) accountId($this->vendor, '1000');
    $equity = (string) accountId($this->vendor, '3000');

    Livewire::actingAs($owner)->test('vendor.accounting.journal.form')
        ->set('memo', 'Capital')
        ->set('lines', [
            ['account_id' => $cash, 'description' => '', 'debit' => '100', 'credit' => ''],
            ['account_id' => $equity, 'description' => '', 'debit' => '', 'credit' => '90'],
        ])
        ->call('post')
        ->assertHasErrors('lines');

    expect(JournalEntry::count())->toBe(0);

    Livewire::actingAs($owner)->test('vendor.accounting.journal.form')
        ->set('memo', 'Capital')
        ->set('lines', [
            ['account_id' => $cash, 'description' => '', 'debit' => '100', 'credit' => ''],
            ['account_id' => $equity, 'description' => '', 'debit' => '', 'credit' => '100'],
        ])
        ->call('post')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(JournalEntry::sole()->is_balanced)->toBeTrue();
});

test('reversing from the entry page posts a mirror entry', function () {
    $owner = accountingUser($this->vendor);
    $entry = postEntry($this->vendor, now()->toDateString(), '1000', '3000', '10.00');

    Livewire::actingAs($owner)->test('vendor.accounting.journal.show', ['entry' => $entry])
        ->set('reason', 'Mistake')
        ->call('reverse');

    expect($entry->fresh()->reversed_by_id)->not->toBeNull();
});

test('journal export downloads', function () {
    $owner = accountingUser($this->vendor);
    postEntry($this->vendor, '2026-05-05', '1000', '3000', '10.00');

    Livewire::actingAs($owner)->test('vendor.accounting.journal.index')
        ->set('from', '2026-01-01')->set('to', '2026-12-31')
        ->call('export')
        ->assertFileDownloaded('journal-entries-2026-01-01-to-2026-12-31.xlsx');
});

test('cash flow export downloads and ties', function () {
    $owner = accountingUser($this->vendor);
    postEntry($this->vendor, '2026-05-05', '1000', '3000', '10.00');

    $c = Livewire::actingAs($owner)->test('vendor.accounting.reports.cash-flow')
        ->set('from', '2026-01-01')->set('to', '2026-12-31');

    expect($c->get('report')['ties'])->toBeTrue();
    $c->call('export')->assertFileDownloaded('cash-flow-2026-01-01-to-2026-12-31.xlsx');
});
