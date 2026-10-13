<?php

use App\Models\Client;
use App\Models\FiscalPeriod;
use App\Models\FiscalPeriodEvent;
use App\Models\Payment;
use App\Services\Accounting\Exceptions\ClosedPeriodException;
use App\Services\Accounting\FiscalPeriodService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor();
    $this->owner = accountingUser($this->vendor);
    $this->periods = app(FiscalPeriodService::class);
    $this->periods->createQuarters($this->vendor->id, 2026);
    $this->q1 = FiscalPeriod::withoutGlobalScope('vendor')->where('vendor_id', $this->vendor->id)->where('name', '2026-Q1')->sole();
});

test('quarters are created once and cannot overlap', function () {
    $this->periods->createQuarters($this->vendor->id, 2026);

    expect(FiscalPeriod::withoutGlobalScope('vendor')->where('vendor_id', $this->vendor->id)->count())->toBe(4);
    expect(fn () => $this->periods->create($this->vendor->id, 'Overlap', now()->setDate(2026, 3, 1), now()->setDate(2026, 4, 30)))
        ->toThrow(ValidationException::class);
});

test('cannot post into a closed period', function () {
    $this->periods->close($this->q1, $this->owner);

    expect(fn () => postEntry($this->vendor, '2026-02-15', '1000', '4000', '10.00'))->toThrow(ClosedPeriodException::class);
    expect(postEntry($this->vendor, '2026-04-02', '1000', '4000', '10.00')->id)->toBeInt();
});

test('a payment dated inside a closed period is refused before it is saved', function () {
    $this->periods->close($this->q1, $this->owner);
    $client = Client::withoutGlobalScope('vendor')->create(['vendor_id' => $this->vendor->id, 'account_no' => 'C1', 'name' => 'X']);

    expect(fn () => Payment::withoutGlobalScope('vendor')->create([
        'vendor_id' => $this->vendor->id, 'client_id' => $client->id, 'amount' => '10', 'method' => 'cash', 'paid_at' => '2026-03-01',
    ]))->toThrow(ClosedPeriodException::class);

    expect(Payment::withoutGlobalScope('vendor')->count())->toBe(0);
});

test('reopening needs the permission and a reason, and is logged', function () {
    $this->periods->close($this->q1, $this->owner);
    $staff = accountingUser($this->vendor, 'vendor_staff');

    expect(fn () => $this->periods->reopen($this->q1->fresh(), $staff, 'please'))->toThrow(AuthorizationException::class);
    expect(fn () => $this->periods->reopen($this->q1->fresh(), $this->owner, '  '))->toThrow(ValidationException::class);

    $this->periods->reopen($this->q1->fresh(), $this->owner, 'Late supplier invoice');

    expect($this->q1->fresh()->is_closed)->toBeFalse()
        ->and(FiscalPeriodEvent::where('fiscal_period_id', $this->q1->id)->pluck('action')->all())->toBe(['closed', 'reopened']);
});

test('staff cannot close or reopen periods from the page', function () {
    $staff = accountingUser($this->vendor, 'vendor_staff');

    Livewire::actingAs($staff)->test('vendor.accounting.periods.index')
        ->call('confirm', $this->q1->id, 'close-period')
        ->call('close')
        ->assertForbidden();

    expect($this->q1->fresh()->is_closed)->toBeFalse();
});

test('owners can close a period from the page', function () {
    Livewire::actingAs($this->owner)->test('vendor.accounting.periods.index')
        ->call('confirm', $this->q1->id, 'close-period')
        ->call('close');

    expect($this->q1->fresh()->is_closed)->toBeTrue();
});
