<?php

use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->user = accountingUser($this->vendor);
    postEntry($this->vendor, '2026-01-15', '1000', '3000', '5000.00');
    postEntry($this->vendor, '2026-03-15', '1100', '4000', '800.00');
});

test('trial balance page renders', function () {
    $this->actingAs($this->user)->get(route('vendor.accounting.reports.trial-balance'))->assertOk()->assertSee('Trial balance');
});

test('the as-of date filters the balances', function () {
    $component = Livewire::actingAs($this->user)->test('vendor.accounting.reports.trial-balance')
        ->set('asOf', '2026-02-01');

    expect($component->get('report')['total_debit'])->toBe('5000.00');

    $component->set('asOf', '2026-12-31');
    expect($component->get('report')['total_debit'])->toBe('5800.00')
        ->and($component->get('report')['balanced'])->toBeTrue();
});

test('export downloads an xlsx', function () {
    Livewire::actingAs($this->user)->test('vendor.accounting.reports.trial-balance')
        ->set('asOf', '2026-12-31')
        ->call('export')
        ->assertFileDownloaded('trial-balance-2026-12-31.xlsx');
});

test('another vendor\'s postings never appear', function () {
    $other = accountingVendor();
    postEntry($other, '2026-03-15', '1000', '4000', '99999.00');

    $component = Livewire::actingAs($this->user)->test('vendor.accounting.reports.trial-balance')->set('asOf', '2026-12-31');
    expect($component->get('report')['total_debit'])->toBe('5800.00');
});
