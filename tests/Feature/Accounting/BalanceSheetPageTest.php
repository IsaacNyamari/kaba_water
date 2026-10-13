<?php

use App\Models\JournalEntry;
use App\Models\Vendor;
use App\Support\Money\MoneyMath;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->user = accountingUser($this->vendor);
});

test('balances on seeded demo data', function () {
    $this->seed(DatabaseSeeder::class);
    $demo = Vendor::where('slug', 'demo-water')->first();
    $user = accountingUser($demo);

    $c = Livewire::actingAs($user)->test('vendor.accounting.reports.balance-sheet');

    $r = $c->get('result')['report'];
    expect($c->get('result')['error'])->toBeNull()
        ->and(MoneyMath::isPositive($r['total_assets']))->toBeTrue()
        ->and($r['total_assets'])->toBe($r['total_liabilities_and_equity']);
    $c->assertSee('Balanced');
});

test('refuses to show numbers when out of balance', function () {
    postEntry($this->vendor, now()->toDateString(), '1000', '3000', '100.00');
    $entry = JournalEntry::withoutGlobalScope('vendor')->create(['vendor_id' => $this->vendor->id, 'date' => now()->toDateString(), 'memo' => 'corrupt']);
    DB::table('journal_lines')->insert(['journal_entry_id' => $entry->id, 'account_id' => accountId($this->vendor, '1000'), 'debit' => '1.00', 'credit' => '0', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('journal_entries')->where('id', $entry->id)->update(['posted_at' => now()]);

    Livewire::actingAs($this->user)->test('vendor.accounting.reports.balance-sheet')
        ->assertSee('The balance sheet does not balance')
        ->assertDontSee('Total assets');
});

test('export downloads', function () {
    postEntry($this->vendor, '2026-01-01', '1000', '3000', '100.00');

    Livewire::actingAs($this->user)->test('vendor.accounting.reports.balance-sheet')
        ->set('asOf', '2026-06-30')
        ->call('export')
        ->assertFileDownloaded('balance-sheet-2026-06-30.xlsx');
});
