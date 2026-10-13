<?php

use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->user = accountingUser($this->vendor);
    // February: revenue 1,000, expense 400. March: revenue 2,500, expense 1,000.
    postEntry($this->vendor, '2026-02-10', '1100', '4000', '1000.00');
    postEntry($this->vendor, '2026-02-20', '5100', '1000', '400.00');
    postEntry($this->vendor, '2026-03-10', '1100', '4000', '2500.00');
    postEntry($this->vendor, '2026-03-20', '5200', '1000', '1000.00');
});

test('net income is computed for the selected period', function () {
    $c = Livewire::actingAs($this->user)->test('vendor.accounting.reports.income-statement')
        ->set('from', '2026-03-01')->set('to', '2026-03-31');

    expect($c->get('report')['net_income'])->toBe('1500.00')
        ->and($c->get('report')['total_revenue'])->toBe('2500.00');
});

test('prior-period comparison toggles on and covers the preceding period', function () {
    $c = Livewire::actingAs($this->user)->test('vendor.accounting.reports.income-statement')
        ->set('from', '2026-03-01')->set('to', '2026-03-31');

    expect($c->get('prior'))->toBeNull();

    $c->set('compare', true);
    expect($c->get('prior')['net_income'])->toBe('600.00')
        ->and($c->get('prior')['to']->toDateString())->toBe('2026-02-28');

    $c->assertSee('Prior period');
});

test('export downloads', function () {
    Livewire::actingAs($this->user)->test('vendor.accounting.reports.income-statement')
        ->set('from', '2026-03-01')->set('to', '2026-03-31')
        ->call('export')
        ->assertFileDownloaded('income-statement-2026-03-01-to-2026-03-31.xlsx');
});
