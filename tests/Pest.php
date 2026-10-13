<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit/Accounting');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Accounting helpers
|--------------------------------------------------------------------------
*/

/** A vendor workspace with its standard chart of accounts. */
function accountingVendor(array $attributes = []): \App\Models\Vendor
{
    return \App\Models\Vendor::factory()->create($attributes); // VendorObserver seeds the chart
}

/** A verified user of $vendor holding $role (roles/permissions seeded on demand). */
function accountingUser(\App\Models\Vendor $vendor, string $role = 'vendor'): \App\Models\User
{
    if (! \Spatie\Permission\Models\Role::where('name', $role)->exists()) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = \App\Models\User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole($role);

    return $user;
}

/** Account id for a code in a vendor's chart. */
function accountId(\App\Models\Vendor $vendor, string $code): int
{
    return (int) \App\Models\ChartOfAccount::withoutGlobalScope('vendor')
        ->where('vendor_id', $vendor->id)->where('code', $code)->value('id');
}

/** Post a simple two-line entry through the ledger. */
function postEntry(\App\Models\Vendor $vendor, string $date, string $debitCode, string $creditCode, string $amount, string $memo = 'Test'): \App\Models\JournalEntry
{
    return app(\App\Services\Accounting\LedgerService::class)->post([
        ['account_code' => $debitCode, 'debit' => $amount],
        ['account_code' => $creditCode, 'credit' => $amount],
    ], ['vendor_id' => $vendor->id, 'date' => $date, 'memo' => $memo]);
}
