<?php

use App\Models\ChartOfAccount;
use App\Models\Vendor;
use App\Services\Accounting\ChartOfAccountsService;

test('new vendors get the standard chart, marked as system accounts', function () {
    $vendor = accountingVendor();

    $accounts = ChartOfAccount::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->get();

    expect($accounts)->toHaveCount(count(ChartOfAccountsService::standardChart()))
        ->and($accounts->every->is_system)->toBeTrue()
        ->and($accounts->firstWhere('code', '1510')->normal_side->value)->toBe('credit')
        ->and($accounts->firstWhere('code', '4000')->type->value)->toBe('revenue');
});

test('seeding is idempotent', function () {
    $vendor = accountingVendor();
    $service = app(ChartOfAccountsService::class);

    $service->seedDefaultsFor($vendor);
    $service->seedDefaultsFor($vendor);

    expect(ChartOfAccount::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->count())
        ->toBe(count(ChartOfAccountsService::standardChart()));
});

test('existing codes are skipped and customisations kept', function () {
    $vendor = Vendor::withoutEvents(fn () => Vendor::factory()->create());
    ChartOfAccount::withoutGlobalScope('vendor')->create([
        'vendor_id' => $vendor->id, 'code' => '1000', 'name' => 'Main M-Pesa till',
        'type' => 'asset', 'normal_side' => 'debit', 'is_system' => false,
    ]);

    app(ChartOfAccountsService::class)->seedDefaultsFor($vendor);

    $cash = ChartOfAccount::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->where('code', '1000')->sole();
    expect($cash->name)->toBe('Main M-Pesa till')->and($cash->is_system)->toBeFalse();
    expect(ChartOfAccount::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->count())
        ->toBe(count(ChartOfAccountsService::standardChart()));
});

test('ensureFor only seeds on first use', function () {
    $vendor = Vendor::withoutEvents(fn () => Vendor::factory()->create());
    $service = app(ChartOfAccountsService::class);

    $service->ensureFor($vendor);
    ChartOfAccount::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->where('code', '5900')->forceDelete();
    $service->ensureFor($vendor); // has accounts already → no top-up

    expect(ChartOfAccount::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->where('code', '5900')->exists())->toBeFalse();
});
