<?php

use App\Enums\InvoiceStatus;
use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\User;
use App\Services\Accounting\FiscalPeriodService;
use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->owner = accountingUser($this->vendor);
    $this->client = Client::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'account_no' => 'C00001', 'name' => 'Jane Customer',
    ]);
});

function guardInvoice($test, string $status, $issuedAt = null): Invoice
{
    return Invoice::withoutGlobalScope('vendor')->create([
        'vendor_id' => $test->vendor->id, 'client_id' => $test->client->id,
        'number' => 'INV-G-'.random_int(1000, 9999),
        'issued_at' => $issuedAt ?? now(), 'due_at' => now()->addDays(14),
        'subtotal' => '1000.00', 'tax' => '160.00', 'total' => '1160.00', 'status' => $status,
    ]);
}

function closePeriodAround($test, string $date): void
{
    $periods = app(FiscalPeriodService::class);
    $periods->createQuarters($test->vendor->id, (int) substr($date, 0, 4));

    $period = FiscalPeriod::withoutGlobalScope('vendor')->where('vendor_id', $test->vendor->id)->forDate($date)->sole();
    $periods->close($period, $test->owner);
}

test('sending an invoice dated in a closed period is refused without an error page', function () {
    $invoice = guardInvoice($this, 'draft', '2026-02-10');
    closePeriodAround($this, '2026-02-10');

    Livewire::actingAs($this->owner)->test('vendor.invoices.show', ['invoice' => $invoice])
        ->call('send')
        ->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft)
        ->and(JournalEntry::withoutGlobalScope('vendor')->where('vendor_id', $this->vendor->id)->count())->toBe(0);
});

test('voiding while today is in a closed period is refused without an error page', function () {
    $invoice = guardInvoice($this, 'sent');
    closePeriodAround($this, now()->toDateString());

    Livewire::actingAs($this->owner)->test('vendor.invoices.show', ['invoice' => $invoice])
        ->call('void')
        ->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('accounts used by automatic postings cannot be deactivated', function () {
    $receivables = ChartOfAccount::withoutGlobalScope('vendor')->find(accountId($this->vendor, '1100'));
    $inventory = ChartOfAccount::withoutGlobalScope('vendor')->find(accountId($this->vendor, '1200'));

    Livewire::actingAs($this->owner)->test('vendor.accounting.accounts.index')
        ->call('toggleActive', $receivables->id)
        ->call('toggleActive', $inventory->id);

    expect($receivables->fresh()->is_active)->toBeTrue()
        ->and($inventory->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($this->owner)->test('vendor.accounting.accounts.form', ['account' => $receivables])
        ->set('is_active', false)
        ->call('save')
        ->assertHasErrors('is_active');

    expect($receivables->fresh()->is_active)->toBeTrue();
});

test('entries posted from a document cannot be reversed from the journal page', function () {
    $invoice = guardInvoice($this, 'sent');
    $entry = JournalEntry::withoutGlobalScope('vendor')->where('source_type', Invoice::class)->where('source_id', $invoice->id)->sole();

    Livewire::actingAs($this->owner)->test('vendor.accounting.journal.show', ['entry' => $entry])
        ->set('reason', 'Trying anyway')
        ->call('reverse');

    expect($entry->fresh()->reversed_by_id)->toBeNull();
});

test('reversing the opening entry reopens the wizard', function () {
    Livewire::actingAs($this->owner)->test('vendor.accounting.opening-balances')->set('cash', '100')->call('post');

    $opening = OpeningBalance::withoutGlobalScope('vendor')->where('vendor_id', $this->vendor->id)->sole();
    $entry = JournalEntry::withoutGlobalScope('vendor')->find($opening->journal_entry_id);

    Livewire::actingAs($this->owner)->test('vendor.accounting.journal.show', ['entry' => $entry])
        ->set('reason', 'Wrong figures')
        ->call('reverse');

    expect($opening->fresh()->is_posted)->toBeFalse();

    Livewire::actingAs($this->owner)->test('vendor.accounting.opening-balances')->set('cash', '250')->call('post');

    $opening->refresh();
    $cash = ChartOfAccount::withoutGlobalScope('vendor')->find(accountId($this->vendor, '1000'));

    expect($opening->is_posted)->toBeTrue()
        ->and($opening->journal_entry_id)->not->toBe($entry->id)
        ->and((string) $cash->balanceAsOf(now())->getAmount())->toBe('250.00');
});

test('writing off from the invoice page needs accounting.post', function () {
    $invoice = guardInvoice($this, 'sent');

    $clerk = User::factory()->create(['vendor_id' => $this->vendor->id]);
    $clerk->givePermissionTo(['vendor.invoices.view', 'vendor.invoices.manage']);

    Livewire::actingAs($clerk)->test('vendor.invoices.show', ['invoice' => $invoice])
        ->call('writeOff')
        ->assertForbidden();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);

    Livewire::actingAs($this->owner)->test('vendor.invoices.show', ['invoice' => $invoice])
        ->call('writeOff');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::WrittenOff);
});
