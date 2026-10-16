<?php

use App\Actions\InvoiceReadingAutomatically;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\Tariff;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->actingAs(accountingUser($this->vendor));
    $client = Client::create(['account_no' => 'C00001', 'name' => 'Jane', 'phone' => '0712345678']);
    $this->meter = Meter::create(['client_id' => $client->id, 'serial' => 'AUTO-1', 'status' => 'active']);
    Tariff::ensureDefaultFor($this->vendor->id)->update(['rate' => '100']);
});

test('a reading after a previous one is billed and issued straight away', function () {
    Reading::create(['meter_id' => $this->meter->id, 'reading' => 100, 'previous_reading' => 0, 'read_at' => now()->subMonth()]);
    $reading = Reading::create(['meter_id' => $this->meter->id, 'reading' => 112, 'read_at' => now()]);

    $result = app(InvoiceReadingAutomatically::class)->handle($reading->fresh());

    expect($result['invoice'])->not->toBeNull()
        ->and($result['invoice']->status)->toBe(InvoiceStatus::Sent)
        ->and((string) $result['invoice']->total)->toBe('1200.00')
        ->and($reading->fresh()->invoice)->not->toBeNull();
});

test('every vendor starts with the default KES 200 / m³ tariff', function () {
    $tariff = Tariff::withoutGlobalScope('vendor')->where('vendor_id', accountingVendor()->id)->sole();

    expect($tariff->name)->toBe(Tariff::DEFAULT_NAME)
        ->and($tariff->type)->toBe('flat')
        ->and((string) $tariff->rate)->toBe('200.0000')
        ->and($tariff->is_active)->toBeTrue();
});

test('the first reading of a meter is not billed, and billing twice is harmless', function () {
    $first = Reading::create(['meter_id' => $this->meter->id, 'reading' => 50, 'previous_reading' => 0, 'read_at' => now()->subMonth()]);
    expect(app(InvoiceReadingAutomatically::class)->handle($first->fresh())['invoice'])->toBeNull();

    $second = Reading::create(['meter_id' => $this->meter->id, 'reading' => 60, 'read_at' => now()]);
    app(InvoiceReadingAutomatically::class)->handle($second->fresh());
    app(InvoiceReadingAutomatically::class)->handle($second->fresh());

    expect($second->fresh()->invoice()->count())->toBe(1);
});
