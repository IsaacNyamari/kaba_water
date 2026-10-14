<?php

use App\Actions\GenerateInvoiceFromReading;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\Tariff;
use App\Services\InvoicePdfService;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 0));

    $this->vendor = accountingVendor();
    $this->user = accountingUser($this->vendor, 'vendor');

    $client = Client::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'account_no' => 'C00001', 'name' => 'Jane Customer',
    ]);
    $this->meter = Meter::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'client_id' => $client->id, 'serial' => 'KW-0001', 'status' => 'active',
    ]);
});

/** An approved reading recorded directly, as if entered earlier. */
function recordReading($test, string $readAt, int $value, ?int $previous = null): Reading
{
    return Reading::withoutGlobalScope('vendor')->create([
        'vendor_id' => $test->vendor->id,
        'meter_id' => $test->meter->id,
        'reading' => $value,
        'previous_reading' => $previous,
        'read_at' => $readAt,
        'status' => 'approved',
    ]);
}

function readingForm($test)
{
    return Livewire::actingAs($test->user)
        ->test('vendor.readings.create')
        ->set('meter_id', (string) $test->meter->id);
}

function invoiceFor($test, Reading $reading): Invoice
{
    return Invoice::withoutGlobalScope('vendor')->create([
        'vendor_id' => $test->vendor->id, 'client_id' => $test->meter->client_id, 'reading_id' => $reading->id,
        'number' => 'INV-T-'.$reading->id, 'issued_at' => $reading->read_at, 'due_at' => $reading->read_at->copy()->addDays(14),
        'subtotal' => '100.00', 'tax' => '0.00', 'total' => '100.00', 'status' => 'draft',
    ]);
}

test('the current month and the eleven before it can be chosen', function () {
    $months = array_keys(readingForm($this)->instance()->billingMonths());

    expect($months)->toHaveCount(12)
        ->and($months[0])->toBe('2026-10')
        ->and($months[11])->toBe('2025-11');
});

test('choosing a past month moves the reading date to the end of that month', function () {
    readingForm($this)
        ->set('billing_month', '2026-02')
        ->assertSet('read_at', '2026-02-28T12:00')
        ->set('billing_month', '2026-10')
        ->assertSet('read_at', '2026-10-14T10:00');
});

test('a month outside the allowed range is refused', function () {
    readingForm($this)
        ->set('billing_month', '2025-01')
        ->assertSet('billing_month', '2026-10');
});

test('a backdated reading measures consumption from the reading before it and re-links the one after', function () {
    recordReading($this, '2026-01-31 12:00', 100, 0);
    $march = recordReading($this, '2026-03-31 12:00', 300);

    readingForm($this)
        ->set('billing_month', '2026-02')
        ->assertSet('previous_reading', '100')
        ->set('reading', '180')
        ->call('save')
        ->assertHasNoErrors();

    $february = Reading::withoutGlobalScope('vendor')->whereMonth('read_at', 2)->sole();

    expect((int) $february->previous_reading)->toBe(100)
        ->and((int) $february->consumption)->toBe(80)
        ->and((int) $march->fresh()->previous_reading)->toBe(180)
        ->and((int) $march->fresh()->consumption)->toBe(120);
});

test('the earliest backfilled month needs a baseline, which then feeds the later reading', function () {
    $october = recordReading($this, '2026-10-05 09:00', 500, 400);

    readingForm($this)
        ->set('billing_month', '2026-07')
        ->assertSet('previous_reading', '')
        ->set('reading', '300')
        ->call('save')
        ->assertHasErrors(['previous_reading' => 'required']);

    readingForm($this)
        ->set('billing_month', '2026-07')
        ->set('previous_reading', '250')
        ->set('reading', '300')
        ->call('save')
        ->assertHasNoErrors();

    $july = Reading::withoutGlobalScope('vendor')->whereMonth('read_at', 7)->sole();

    expect((int) $july->consumption)->toBe(50)
        ->and((int) $october->fresh()->previous_reading)->toBe(300)
        ->and((int) $october->fresh()->consumption)->toBe(200);
});

test('a backdated reading must sit between the readings around it', function () {
    recordReading($this, '2026-01-31 12:00', 100, 0);
    recordReading($this, '2026-03-31 12:00', 300);

    readingForm($this)->set('billing_month', '2026-02')->set('reading', '350')->call('save')
        ->assertHasErrors(['reading' => 'lte']);

    readingForm($this)->set('billing_month', '2026-02')->set('reading', '90')->call('save')
        ->assertHasErrors(['reading' => 'gte']);

    expect(Reading::withoutGlobalScope('vendor')->count())->toBe(2);
});

test('a meter can only be read once per month', function () {
    recordReading($this, '2026-05-10 12:00', 100, 0);

    readingForm($this)
        ->set('billing_month', '2026-05')
        ->set('reading', '150')
        ->call('save')
        ->assertHasErrors(['meter_id']);

    expect(Reading::withoutGlobalScope('vendor')->count())->toBe(1);
});

test('an earlier month cannot be added in front of an invoiced reading', function () {
    recordReading($this, '2026-01-31 12:00', 100, 0);
    $march = recordReading($this, '2026-03-31 12:00', 300);
    invoiceFor($this, $march);

    readingForm($this)
        ->set('billing_month', '2026-02')
        ->set('reading', '180')
        ->call('save')
        ->assertHasErrors(['meter_id']);

    expect((int) $march->fresh()->previous_reading)->toBe(100)
        ->and((int) $march->fresh()->consumption)->toBe(200);
});

test('the reading date must fall inside the chosen month and not in the future', function () {
    readingForm($this)
        ->set('billing_month', '2026-02')
        ->set('read_at', '2026-03-02T12:00')
        ->set('previous_reading', '0')
        ->set('reading', '10')
        ->call('save')
        ->assertHasErrors(['read_at']);

    readingForm($this)
        ->set('read_at', '2026-10-20T12:00')
        ->set('previous_reading', '0')
        ->set('reading', '10')
        ->call('save')
        ->assertHasErrors(['read_at']);
});

test('approving an older pending reading re-links the reading after it', function () {
    recordReading($this, '2026-01-31 12:00', 100, 0);
    $march = recordReading($this, '2026-03-31 12:00', 300);

    $february = Reading::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'meter_id' => $this->meter->id,
        'reading' => 160, 'read_at' => '2026-02-27 12:00', 'status' => 'pending',
    ]);

    expect((int) $march->fresh()->previous_reading)->toBe(100);

    $february->approve();

    expect((int) $february->fresh()->consumption)->toBe(60)
        ->and((int) $march->fresh()->previous_reading)->toBe(160)
        ->and((int) $march->fresh()->consumption)->toBe(140);
});

test('an invoice for a past month is dated in that month; this month is dated today', function () {
    $this->mock(InvoicePdfService::class)->shouldReceive('store')->andReturn('invoices/test.pdf');
    $this->actingAs($this->user);

    $tariff = Tariff::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'name' => 'Flat', 'type' => 'flat', 'rate' => '100', 'is_active' => true,
    ]);

    recordReading($this, '2026-03-01 08:00', 100, 0);
    $past = recordReading($this, '2026-03-31 12:00', 120);
    $current = recordReading($this, '2026-10-05 09:00', 150);

    $action = app(GenerateInvoiceFromReading::class);
    $pastInvoice = $action->handle($past, $tariff, 0, 14);
    $currentInvoice = $action->handle($current, $tariff, 0, 14);

    expect($pastInvoice->issued_at->toDateString())->toBe('2026-03-31')
        ->and($pastInvoice->due_at->toDateString())->toBe('2026-04-14')
        ->and($currentInvoice->issued_at->toDateString())->toBe('2026-10-14')
        ->and($currentInvoice->due_at->toDateString())->toBe('2026-10-28');
});
