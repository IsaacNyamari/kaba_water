<?php

use App\Actions\BillUnit;
use App\Actions\SendInvoice;
use App\Enums\BusinessType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Property;
use App\Models\SmsMessage;
use App\Models\Unit;
use App\Models\Vendor;
use App\Support\Modules;
use Livewire\Livewire;

beforeEach(function () {
    $this->vendor = accountingVendor(['business_type' => BusinessType::Property]);
    $this->user = accountingUser($this->vendor);
    $this->actingAs($this->user);

    $this->tenant = Client::create(['account_no' => 'C00001', 'name' => 'Amina', 'phone' => '0712345678']);
    $this->property = Property::create(['name' => 'Sunrise Apartments']);
    $this->unit = Unit::create([
        'property_id' => $this->property->id,
        'client_id' => $this->tenant->id,
        'house_number' => 'A4',
        'rent' => '15000.00',
        'service_charge' => '1500.00',
    ]);
});

/** Credit total posted to an account code in the vendor's ledger. */
function creditedTo(Vendor $vendor, string $code): string
{
    return number_format((float) JournalLine::where('account_id', accountId($vendor, $code))->sum('credit')
        - (float) JournalLine::where('account_id', accountId($vendor, $code))->sum('debit'), 2, '.', '');
}

test('billing a house creates a draft bill for its house number and tenant', function () {
    $invoice = app(BillUnit::class)->handle($this->unit, '2026-10');

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->unit_id)->toBe($this->unit->id)
        ->and($invoice->client_id)->toBe($this->tenant->id)
        ->and($invoice->billing_period)->toBe('2026-10')
        ->and((string) $invoice->total)->toBe('16500.00')
        ->and($invoice->items->pluck('category')->all())->toBe(['rent', 'service_charge']);
});

test('issuing a house bill posts rent and service charge to their own revenue accounts', function () {
    $invoice = app(BillUnit::class)->handle($this->unit, now()->format('Y-m'), taxRate: 16);
    app(SendInvoice::class)->handle($invoice);

    expect(creditedTo($this->vendor, '4300'))->toBe('15000.00')
        ->and(creditedTo($this->vendor, '4400'))->toBe('1500.00')
        ->and(creditedTo($this->vendor, '2100'))->toBe('2640.00')
        ->and(creditedTo($this->vendor, '4000'))->toBe('0.00');

    $invoice->fresh()->update(['status' => InvoiceStatus::Void]);

    expect(creditedTo($this->vendor, '4300'))->toBe('0.00')
        ->and(creditedTo($this->vendor, '4400'))->toBe('0.00');
});

test('a house is billed at most once a month until that bill is voided', function () {
    $first = app(BillUnit::class)->handle($this->unit, '2026-10');

    expect(fn () => app(BillUnit::class)->handle($this->unit, '2026-10'))
        ->toThrow(RuntimeException::class, 'already billed');

    $first->update(['status' => InvoiceStatus::Void]);

    expect(app(BillUnit::class)->handle($this->unit, '2026-10')->billing_period)->toBe('2026-10');
});

test('a vacant house cannot be billed', function () {
    $this->unit->update(['client_id' => null]);

    expect(fn () => app(BillUnit::class)->handle($this->unit, '2026-10'))
        ->toThrow(RuntimeException::class, 'no tenant');
});

test('the bill houses page bills every unbilled occupied house and texts the tenants', function () {
    $second = Unit::create(['client_id' => $this->tenant->id, 'house_number' => 'B1', 'rent' => '8000.00']);
    $vacant = Unit::create(['house_number' => 'B2', 'rent' => '8000.00']);
    app(BillUnit::class)->handle($second, now()->format('Y-m'));

    Livewire::test('vendor.units.billing')
        ->assertSet('selected', [(string) $this->unit->id])
        ->call('generate')
        ->assertHasNoErrors();

    $bill = Invoice::where('unit_id', $this->unit->id)->sole();
    expect($bill->status)->toBe(InvoiceStatus::Sent)
        ->and(Invoice::where('unit_id', $second->id)->count())->toBe(1)
        ->and(Invoice::where('unit_id', $vacant->id)->exists())->toBeFalse()
        ->and(SmsMessage::where('purpose', 'invoice')->sole()->message)->toContain('House A4');
});

test('adding a house with a new tenant creates the tenant with that house number', function () {
    Livewire::test('vendor.units.index')
        ->set('house_number', 'C7')
        ->set('property_id', $this->property->id)
        ->set('tenant_mode', 'new')
        ->set('tenant_name', 'Brian')
        ->set('tenant_phone', '0700111222')
        ->set('rent', '12000')
        ->call('save')
        ->assertHasNoErrors();

    $unit = Unit::where('house_number', 'C7')->sole();
    expect($unit->client->name)->toBe('Brian')
        ->and($unit->client->house_number)->toBe('C7')
        ->and((string) $unit->rent)->toBe('12000.00');
});

test('a house number cannot repeat within the same property', function () {
    Livewire::test('vendor.units.index')
        ->set('house_number', 'A4')
        ->set('property_id', $this->property->id)
        ->call('save')
        ->assertHasErrors(['house_number' => 'unique']);
});

test('a vendor only sees its own houses', function () {
    $other = accountingVendor(['business_type' => BusinessType::Property]);
    Unit::withoutGlobalScope('vendor')->create(['vendor_id' => $other->id, 'house_number' => 'Z9']);

    Livewire::test('vendor.units.index')
        ->assertSee('A4')
        ->assertDontSee('Z9');

    $this->actingAs(accountingUser($other));
    expect(fn () => Livewire::test('vendor.units.billing')->set('selected', [(string) $this->unit->id])->call('generate'))
        ->not->toThrow(Throwable::class);
    expect(Invoice::withoutGlobalScope('vendor')->where('unit_id', $this->unit->id)->exists())->toBeFalse();
});

test('the billing module is named after the business type', function () {
    expect(Modules::for($this->user)['water']['name'])->toBe('HS Property Billing');

    $this->vendor->update(['business_type' => BusinessType::Water]);

    expect(Modules::for($this->user->fresh())['water']['name'])->toBe('HS Water Billing');
});

test('property owners see houses and tenants in the menu', function () {
    $this->get(route('vendor.units.index'))
        ->assertOk()
        ->assertSee('Bill houses')
        ->assertSee('Tenants');
});

test('a landlord can add several apartments', function () {
    Livewire::test('vendor.apartments.index')
        ->set('name', 'Greenview Court')
        ->call('save')
        ->assertHasNoErrors()
        ->set('name', 'Riverside Flats')
        ->call('save')
        ->assertHasNoErrors()
        ->set('name', 'Riverside Flats')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect(Property::orderBy('name')->pluck('name')->all())
        ->toBe(['Greenview Court', 'Riverside Flats', 'Sunrise Apartments']);
});

test('many houses can be added to an apartment at once, skipping ones that exist', function () {
    Livewire::test('vendor.apartments.index')
        ->call('openAddHouses', $this->property->id)
        ->set('prefix', 'A')
        ->set('from', '1')
        ->set('to', '6')
        ->set('rent', '15000')
        ->set('service_charge', '1000')
        ->call('addHouses')
        ->assertHasNoErrors();

    $houses = Unit::where('property_id', $this->property->id)->orderBy('id')->get();

    // A4 already existed (with its tenant) and is left alone.
    expect($houses->pluck('house_number')->sort()->values()->all())->toBe(['A1', 'A2', 'A3', 'A4', 'A5', 'A6'])
        ->and($houses->firstWhere('house_number', 'A4')->client_id)->toBe($this->tenant->id)
        ->and((string) $houses->firstWhere('house_number', 'A6')->rent)->toBe('15000.00')
        ->and($houses->firstWhere('house_number', 'A6')->client_id)->toBeNull();
});

test('adding houses is capped and needs a valid range', function () {
    Livewire::test('vendor.apartments.index')
        ->call('openAddHouses', $this->property->id)
        ->set('from', '10')
        ->set('to', '5')
        ->call('addHouses')
        ->assertHasErrors(['to'])
        ->set('from', '1')
        ->set('to', '1000')
        ->call('addHouses')
        ->assertHasErrors(['to']);

    expect(Unit::count())->toBe(1);
});

test('the apartments page shows each building with its occupancy and rent roll', function () {
    Unit::create(['property_id' => $this->property->id, 'house_number' => 'A5', 'rent' => '9000.00']);

    Livewire::test('vendor.apartments.index')
        ->assertSee('Sunrise Apartments')
        ->assertSee('16,500.00'); // only the occupied A4 counts
});

test('an apartment with houses cannot be deleted', function () {
    $empty = Property::create(['name' => 'Empty Block']);

    Livewire::test('vendor.apartments.index')
        ->call('confirmDelete', $this->property->id)
        ->call('delete')
        ->call('confirmDelete', $empty->id)
        ->call('delete');

    expect(Property::pluck('name')->all())->toBe(['Sunrise Apartments']);
});
