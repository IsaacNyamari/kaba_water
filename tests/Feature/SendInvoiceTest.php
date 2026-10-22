<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\SmsMessage;
use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->user = accountingUser($this->vendor);
    $this->actingAs($this->user);
    $this->client = Client::create(['account_no' => 'C00001', 'name' => 'Jane', 'phone' => '0712345678']);
});

function sendableInvoice($test, string $status): Invoice
{
    return Invoice::create([
        'vendor_id' => $test->vendor->id,
        'client_id' => $test->client->id,
        'number' => 'INV-'.fake()->unique()->numerify('#####'),
        'issued_at' => now(),
        'due_at' => now()->addDays(14),
        'subtotal' => 500,
        'tax' => 0,
        'total' => 500,
        'status' => $status,
    ]);
}

test('sending a draft from the invoice page issues it and texts the client', function () {
    $invoice = sendableInvoice($this, 'draft');

    Livewire::test('vendor.invoices.show', ['invoice' => $invoice])->call('send')->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and(SmsMessage::where('purpose', 'invoice')->count())->toBe(1);
});

test('an unpaid invoice can be resent from the invoices list', function () {
    $invoice = sendableInvoice($this, 'sent');

    Livewire::test('vendor.invoices.index')->call('send', $invoice->id)->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and(SmsMessage::where('purpose', 'invoice')->count())->toBe(1);
});

test('a void invoice is not sent', function () {
    $invoice = sendableInvoice($this, 'void');

    Livewire::test('vendor.invoices.index')->call('send', $invoice->id)->assertOk();

    expect(SmsMessage::count())->toBe(0);
});
