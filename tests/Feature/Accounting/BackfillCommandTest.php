<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;

test('backfill posts pre-existing documents once', function () {
    $vendor = accountingVendor();
    $client = Client::withoutGlobalScope('vendor')->create(['vendor_id' => $vendor->id, 'account_no' => 'C1', 'name' => 'Old customer']);

    // Simulate data created before the ledger existed (observers off).
    $invoice = Invoice::withoutEvents(fn () => Invoice::withoutGlobalScope('vendor')->create([
        'vendor_id' => $vendor->id, 'client_id' => $client->id, 'number' => 'INV-OLD-1',
        'issued_at' => '2026-01-10', 'due_at' => '2026-01-24',
        'subtotal' => '100.00', 'tax' => '0', 'total' => '100.00', 'amount_paid' => '100.00', 'status' => 'paid',
    ]));
    Payment::withoutEvents(fn () => Payment::withoutGlobalScope('vendor')->create([
        'vendor_id' => $vendor->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
        'amount' => '100.00', 'method' => 'cash', 'paid_at' => '2026-01-20',
    ]));

    $this->artisan('accounting:backfill')->assertSuccessful();
    $this->artisan('accounting:backfill')->assertSuccessful();

    expect(JournalEntry::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->count())->toBe(2);
});
