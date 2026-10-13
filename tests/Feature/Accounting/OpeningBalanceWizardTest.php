<?php

use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use Livewire\Livewire;

test('posts a single balanced opening entry with equity as the plug', function () {
    $vendor = accountingVendor();
    $user = accountingUser($vendor);

    $c = Livewire::actingAs($user)->test('vendor.accounting.opening-balances')
        ->set('as_of_date', '2025-12-31')->call('next')
        ->set('cash', '50000')->call('next')
        ->set('receivables', '12000.50')->call('next')
        ->set('fixed_assets', '80000')->set('accumulated_depreciation', '8000')->call('next')
        ->set('payables', '20000')->call('next')
        ->assertSet('step', 6);

    expect($c->get('equity'))->toBe('114000.50');

    $c->call('post');

    $opening = OpeningBalance::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->sole();
    $entry = JournalEntry::withoutGlobalScope('vendor')->with('lines')->find($opening->journal_entry_id);

    expect($opening->is_posted)->toBeTrue()
        ->and($entry->is_balanced)->toBeTrue()
        ->and($entry->date->toDateString())->toBe('2025-12-31')
        ->and($entry->lines->firstWhere('account_id', accountId($vendor, '3000'))->credit)->toBe('114000.50');
});

test('cannot be posted twice', function () {
    $vendor = accountingVendor();
    $user = accountingUser($vendor);

    Livewire::actingAs($user)->test('vendor.accounting.opening-balances')->set('cash', '100')->call('post');
    Livewire::actingAs($user)->test('vendor.accounting.opening-balances')->set('cash', '999')->call('post');

    expect(JournalEntry::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->where('source_event', 'opening_balance')->count())->toBe(1);
});

test('negative equity is posted as a debit', function () {
    $vendor = accountingVendor();
    $user = accountingUser($vendor);

    Livewire::actingAs($user)->test('vendor.accounting.opening-balances')
        ->set('cash', '100')->set('receivables', '0')->set('fixed_assets', '0')->set('accumulated_depreciation', '0')
        ->set('payables', '500')->call('post');

    $entry = JournalEntry::withoutGlobalScope('vendor')->with('lines')->where('vendor_id', $vendor->id)->sole();
    expect($entry->lines->firstWhere('account_id', accountId($vendor, '3000'))->debit)->toBe('400.00');
});
