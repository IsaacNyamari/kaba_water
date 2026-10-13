<?php

use App\Http\Responses\PasskeyLoginResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Models\Client;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->vendor = Vendor::factory()->create();
});

function userWithRole(?string $role, ?int $vendorId = null): User
{
    $user = User::factory()->create(['vendor_id' => $vendorId]);
    if ($role) {
        $user->assignRole($role);
    }

    // Portal users always have a linked customer account (vendor form / access-request flow).
    if ($role === 'client') {
        Client::withoutGlobalScope('vendor')->create([
            'vendor_id' => $vendorId, 'user_id' => $user->id, 'account_no' => 'C'.$user->id, 'name' => $user->name,
        ]);
    }

    return $user;
}

function login(User $user)
{
    return test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
}

test('each role lands on its own dashboard after logging in', function (string $role, string $route) {
    $user = userWithRole($role, $role === 'super_admin' ? null : $this->vendor->id);

    login($user)->assertSessionHasNoErrors()->assertRedirect(route($route));
    $this->assertAuthenticatedAs($user);
})->with([
    'platform admin' => ['super_admin', 'admin.dashboard'],
    'vendor owner' => ['vendor', 'vendor.dashboard'],
    'accountant' => ['accountant', 'vendor.accounting.dashboard'],
    'vendor staff' => ['vendor_staff', 'vendor.accounting.dashboard'],
    'client' => ['client', 'portal.dashboard'],
]);

test('each dashboard actually opens for its role', function (string $role, string $route) {
    $user = userWithRole($role, $role === 'super_admin' ? null : $this->vendor->id);

    $this->actingAs($user)->get(route($route))->assertOk();
})->with([
    'platform admin' => ['super_admin', 'admin.dashboard'],
    'vendor owner' => ['vendor', 'vendor.dashboard'],
    'accountant' => ['accountant', 'vendor.accounting.dashboard'],
    'vendor staff' => ['vendor_staff', 'vendor.accounting.dashboard'],
    'client' => ['client', 'portal.dashboard'],
]);

test('a deep link inside the user\'s own area is kept', function () {
    $user = userWithRole('vendor', $this->vendor->id);

    $this->get(route('vendor.invoices.index'))->assertRedirect(route('login')); // remembers the URL
    login($user)->assertRedirect(route('vendor.invoices.index'));
});

test('a remembered link to another role\'s area is ignored', function () {
    $client = userWithRole('client', $this->vendor->id);

    $this->get(route('vendor.clients.index'))->assertRedirect(route('login'));
    login($client)->assertRedirect(route('portal.dashboard'));
});

test('accountants are not sent back to non-accounting vendor pages', function () {
    $accountant = userWithRole('accountant', $this->vendor->id);

    $this->get(route('vendor.payments.index'))->assertRedirect(route('login'));
    login($accountant)->assertRedirect(route('vendor.accounting.dashboard'));
});

test('two-factor and passkey logins use the same role redirect', function () {
    expect(app(TwoFactorLoginResponseContract::class))->toBeInstanceOf(TwoFactorLoginResponse::class)
        ->and(app(PasskeyLoginResponseContract::class))->toBeInstanceOf(PasskeyLoginResponse::class);

    $client = userWithRole('client', $this->vendor->id);
    $request = Request::create('/two-factor-challenge', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $client);

    expect(app(TwoFactorLoginResponseContract::class)->toResponse($request)->getTargetUrl())->toBe(route('portal.dashboard'))
        ->and(app(PasskeyLoginResponseContract::class)->toResponse($request)->getTargetUrl())->toBe(route('portal.dashboard'));
});
