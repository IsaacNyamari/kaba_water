<?php

use App\Actions\Accounting\PostMonthlyDepreciation;
use App\Actions\Accounting\WriteOffInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Support\DefaultExpenseCategories;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->client = Client::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'account_no' => 'C00001', 'name' => 'Jane Customer',
    ]);
});

function makeInvoice($test, string $status = 'draft'): Invoice
{
    return Invoice::withoutGlobalScope('vendor')->create([
        'vendor_id' => $test->vendor->id, 'client_id' => $test->client->id,
        'number' => 'INV-2026-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'issued_at' => now(), 'due_at' => now()->addDays(14),
        'subtotal' => '1000.00', 'tax' => '160.00', 'total' => '1160.00', 'status' => $status,
    ]);
}

function entriesFor(string $type, int $id)
{
    return JournalEntry::withoutGlobalScope('vendor')->with('lines')->where('source_type', $type)->where('source_id', $id)->orderBy('id')->get();
}

test('a draft invoice posts nothing; issuing it posts exactly one balanced entry', function () {
    $invoice = makeInvoice($this);
    expect(entriesFor(Invoice::class, $invoice->id))->toHaveCount(0);

    $invoice->update(['status' => InvoiceStatus::Sent]);
    $invoice->update(['status' => InvoiceStatus::Overdue]); // must not double-post

    $entries = entriesFor(Invoice::class, $invoice->id);
    expect($entries)->toHaveCount(1);

    $e = $entries->first();
    $amount = fn ($code, $side) => (string) $e->lines->firstWhere('account_id', accountId($this->vendor, $code))?->{$side};
    expect($e->is_balanced)->toBeTrue()
        ->and($amount('1100', 'debit'))->toBe('1160.00')
        ->and($amount('4000', 'credit'))->toBe('1000.00')
        ->and($amount('2100', 'credit'))->toBe('160.00');
});

test('an invoice created as sent posts immediately', function () {
    $invoice = makeInvoice($this, 'sent');

    expect(entriesFor(Invoice::class, $invoice->id))->toHaveCount(1);
});

test('voiding an issued invoice posts a credit note', function () {
    $invoice = makeInvoice($this, 'sent');
    $invoice->update(['status' => InvoiceStatus::Void]);

    $entries = entriesFor(Invoice::class, $invoice->id);
    expect($entries)->toHaveCount(2)
        ->and($entries->last()->source_event)->toBe('invoice.voided')
        ->and($entries->last()->is_balanced)->toBeTrue();

    $ar = ChartOfAccount::withoutGlobalScope('vendor')->find(accountId($this->vendor, '1100'));
    expect((string) $ar->balanceAsOf(now())->getAmount())->toBe('0.00');
});

test('voiding a draft posts nothing', function () {
    $invoice = makeInvoice($this);
    $invoice->update(['status' => InvoiceStatus::Void]);

    expect(entriesFor(Invoice::class, $invoice->id))->toHaveCount(0);
});

test('payments post on create and are reversed on delete', function () {
    $invoice = makeInvoice($this, 'sent');
    $payment = Payment::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'invoice_id' => $invoice->id, 'client_id' => $this->client->id,
        'amount' => '500.00', 'method' => 'mpesa', 'paid_at' => now(),
    ]);

    $entries = entriesFor(Payment::class, $payment->id);
    expect($entries)->toHaveCount(1)->and($entries->first()->total_debit)->toBe('500.00');

    $payment->delete();

    $entries = entriesFor(Payment::class, $payment->id);
    expect($entries)->toHaveCount(2)
        ->and($entries->first()->fresh()->reversed_by_id)->toBe($entries->last()->id);
});

test('expenses post by category and re-post when edited', function () {
    DefaultExpenseCategories::seedFor($this->vendor->id);
    $salaries = ExpenseCategory::withoutGlobalScope('vendor')->where('vendor_id', $this->vendor->id)->where('slug', 'salaries')->first();
    $rent = ExpenseCategory::withoutGlobalScope('vendor')->where('vendor_id', $this->vendor->id)->where('slug', 'rent')->first();

    $expense = Expense::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'expense_category_id' => $salaries->id,
        'description' => 'March wages', 'amount' => '300.00', 'tax' => '0', 'payment_method' => 'cash', 'paid_at' => now(),
    ]);

    $first = entriesFor(Expense::class, $expense->id)->first();
    expect($first->lines->firstWhere('account_id', accountId($this->vendor, '5100'))?->debit)->not->toBeNull();

    $expense->update(['expense_category_id' => $rent->id, 'amount' => '350.00']);

    $entries = entriesFor(Expense::class, $expense->id);
    expect($entries)->toHaveCount(3) // original, its reversal, the new posting
        ->and($entries->last()->lines->firstWhere('account_id', accountId($this->vendor, '5900'))->debit)->toBe('350.00');
});

test('writing off an invoice posts bad debt and closes it', function () {
    $invoice = makeInvoice($this, 'sent');

    app(WriteOffInvoice::class)->handle($invoice, 'Customer moved away');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::WrittenOff);
    $writeOff = entriesFor(Invoice::class, $invoice->id)->firstWhere('source_event', 'invoice.written_off');
    expect($writeOff->lines->firstWhere('account_id', accountId($this->vendor, '5300'))->debit)->toBe('1160.00');
});

test('monthly depreciation posts once per asset per month', function () {
    $asset = Asset::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'name' => 'Pump', 'category' => 'pump',
        'purchase_date' => '2026-01-01', 'purchase_cost' => '12000.00', 'salvage_value' => '0',
        'depreciation_rate' => '10.00', 'status' => 'active',
    ]);

    $action = app(PostMonthlyDepreciation::class);
    expect($action->handle($this->vendor->id, CarbonImmutable::parse('2026-02-01')))->toBe(1)
        ->and($action->handle($this->vendor->id, CarbonImmutable::parse('2026-02-01')))->toBe(0);

    $entry = entriesFor(Asset::class, $asset->id)->sole();
    expect($entry->total_debit)->toBe('100.00') // 12,000 × 10% ÷ 12
        ->and($entry->date->toDateString())->toBe('2026-02-28');
});
