<?php

use App\Actions\AccessRequests\AcceptInvitation;
use App\Actions\AccessRequests\ApproveAccessRequest;
use App\Actions\AccessRequests\SubmitAccessRequest;
use App\Enums\AccessRequestStatus;
use App\Enums\BusinessType;
use App\Enums\EmailCheckResult;
use App\Models\AccessRequest;
use App\Models\Client;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\AccessInvitation;
use App\Notifications\AccessRequestSubmitted;
use App\Services\EmailDeliverabilityChecker;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Pretend DNS says every address is (or isn't) deliverable — no network in tests. */
function fakeEmailCheck(EmailCheckResult $result): void
{
    app()->instance(EmailDeliverabilityChecker::class, new class($result) extends EmailDeliverabilityChecker
    {
        public function __construct(private EmailCheckResult $result) {}

        public function check(string $email): array
        {
            return [$this->result, 'faked in tests'];
        }
    });
}

function vendorRequestData(array $overrides = []): array
{
    return array_merge([
        'requested_type' => 'vendor',
        'name' => 'Jane Doe',
        'email' => 'jane@acmewater.test',
        'phone' => '',
        'organization' => 'Acme Water',
        'provider_name' => '',
        'account_reference' => '',
        'message' => '',
    ], $overrides);
}

/** Pull the plain token out of the emailed signup link. */
function inviteTokenFor(AccessRequest $request): string
{
    $token = null;

    Notification::assertSentOnDemand(
        AccessInvitation::class,
        function (AccessInvitation $n, array $channels, AnonymousNotifiable $notifiable) use ($request, &$token) {
            if ($n->request->id !== $request->id) {
                return false;
            }
            parse_str((string) parse_url($n->url, PHP_URL_QUERY), $query);
            $token = $query['token'] ?? null;

            return $notifiable->routes['mail'] === [$request->email => $request->name];
        },
    );

    return $token;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    config(['access.auto_approve' => false]);
    fakeEmailCheck(EmailCheckResult::Deliverable);
});

test('the request access page renders for guests', function () {
    $this->get(route('access.request'))->assertOk()->assertSee('Request access');
});

test('submitting a request stores it as pending and notifies admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::test('access.request')
        ->set('name', 'Jane Doe')
        ->set('email', 'Jane@AcmeWater.test')
        ->set('organization', 'Acme Water')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSet('autoApproved', false);

    $request = AccessRequest::sole();
    expect($request->email)->toBe('jane@acmewater.test')
        ->and($request->status)->toBe(AccessRequestStatus::Pending)
        ->and($request->email_check)->toBe(EmailCheckResult::Deliverable);

    Notification::assertSentTo($admin, AccessRequestSubmitted::class);
    Notification::assertSentOnDemandTimes(AccessInvitation::class, 0);
});

test('vendor requests need a company name', function () {
    Livewire::test('access.request')
        ->set('name', 'Jane')
        ->set('email', 'jane@acmewater.test')
        ->call('submit')
        ->assertHasErrors(['organization' => 'required']);
});

test('the honeypot silently drops bot submissions', function () {
    Livewire::test('access.request')
        ->set('name', 'Bot')
        ->set('email', 'bot@acmewater.test')
        ->set('organization', 'Spam Inc')
        ->set('website', 'http://spam.test')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(AccessRequest::count())->toBe(0);
});

test('auto approve sends the signup link when the email passes the check', function () {
    config(['access.auto_approve' => true]);

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData());

    expect($request->status)->toBe(AccessRequestStatus::Approved)
        ->and($request->auto_approved)->toBeTrue()
        ->and($request->role)->toBe('vendor')
        ->and($request->vendor)->not->toBeNull()
        ->and($request->vendor->name)->toBe('Acme Water');

    expect(inviteTokenFor($request))->not->toBeEmpty();
});

test('auto approve waits for a human when the email fails the check', function () {
    config(['access.auto_approve' => true]);
    fakeEmailCheck(EmailCheckResult::Disposable);

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData(['email' => 'x@mailinator.com']));

    expect($request->status)->toBe(AccessRequestStatus::Pending);
    Notification::assertSentOnDemandTimes(AccessInvitation::class, 0);
});

test('client requests are never auto approved', function () {
    config(['access.auto_approve' => true]);

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData([
        'requested_type' => 'client', 'organization' => '', 'provider_name' => 'Acme Water',
    ]));

    expect($request->status)->toBe(AccessRequestStatus::Pending);
});

test('an approved vendor can sign up once through the link', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData());
    app(ApproveAccessRequest::class)->handle($request, 'vendor', null, null, $admin);

    $token = inviteTokenFor($request->fresh());

    $user = app(AcceptInvitation::class)->handle($request->fresh(), $token, 'Jane Doe', 'Sup3r-secret-pass!');

    expect($user->hasRole('vendor'))->toBeTrue()
        ->and($user->vendor_id)->toBe($request->fresh()->vendor_id)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::Accepted);

    // Single use.
    expect(fn () => app(AcceptInvitation::class)->handle($request->fresh(), $token, 'Again', 'Sup3r-secret-pass!'))
        ->toThrow(ValidationException::class);
});

test('a wrong token cannot be used to sign up', function () {
    $admin = User::factory()->create();
    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData());
    app(ApproveAccessRequest::class)->handle($request, 'vendor', null, null, $admin);

    expect(fn () => app(AcceptInvitation::class)->handle($request->fresh(), 'not-the-token', 'Jane', 'Sup3r-secret-pass!'))
        ->toThrow(ValidationException::class);

    expect(User::where('email', 'jane@acmewater.test')->exists())->toBeFalse();
});

test('an approved client is linked to their customer account', function () {
    $admin = User::factory()->create();
    $vendor = Vendor::create(['name' => 'Acme Water', 'slug' => 'acme', 'email' => 'acme@example.com', 'status' => 'active']);
    $client = Client::withoutEvents(fn () => Client::create([
        'vendor_id' => $vendor->id, 'account_no' => 'C00001', 'name' => 'John', 'email' => 'john@home.test',
    ]));

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData([
        'requested_type' => 'client', 'email' => 'john@home.test', 'name' => 'John',
        'organization' => '', 'provider_name' => 'Acme Water',
    ]));
    app(ApproveAccessRequest::class)->handle($request, 'client', $vendor->id, $client->id, $admin);

    $user = app(AcceptInvitation::class)->handle($request->fresh(), inviteTokenFor($request->fresh()), 'John', 'Sup3r-secret-pass!');

    expect($user->hasRole('client'))->toBeTrue()
        ->and($user->vendor_id)->toBe($vendor->id)
        ->and($client->fresh()->user_id)->toBe($user->id);
});

test('the signup page rejects an unsigned link', function () {
    $admin = User::factory()->create();
    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData());
    app(ApproveAccessRequest::class)->handle($request, 'vendor', null, null, $admin);

    $this->get(route('access.accept', ['accessRequest' => $request->id, 'token' => 'forged']))
        ->assertOk()
        ->assertSee('Link expired');
});

test('only platform admins can open the review queue', function () {
    $vendor = Vendor::create(['name' => 'Acme Water', 'slug' => 'acme', 'email' => 'acme@example.com', 'status' => 'active']);
    $vendorUser = User::factory()->create(['vendor_id' => $vendor->id]);
    $vendorUser->assignRole('vendor');

    $this->actingAs($vendorUser)->get(route('admin.access-requests.index'))->assertForbidden();
});

test('landlords ask for access with their property or business name', function () {
    Livewire::test('access.request')
        ->set('requested_type', AccessRequest::TYPE_LANDLORD)
        ->set('name', 'Peter Kamau')
        ->set('email', 'peter@sunrise.test')
        ->call('submit')
        ->assertHasErrors(['organization' => 'required'])
        ->set('organization', 'Sunrise Apartments')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(AccessRequest::sole()->requested_type)->toBe(AccessRequest::TYPE_LANDLORD);
});

test('an approved landlord gets a property-owner workspace and lands on its dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData([
        'requested_type' => AccessRequest::TYPE_LANDLORD, 'email' => 'peter@sunrise.test', 'organization' => 'Sunrise Apartments',
    ]));

    $this->actingAs($admin);
    Livewire::test('admin.access-requests.index')
        ->call('openApprove', $request->id)
        ->assertSet('role', 'vendor')
        ->assertSet('businessType', 'property')
        ->call('approve')
        ->assertHasNoErrors();

    $request->refresh();
    expect($request->vendor->business_type)->toBe(BusinessType::Property)
        ->and($request->vendor->name)->toBe('Sunrise Apartments');

    $user = app(AcceptInvitation::class)->handle($request, inviteTokenFor($request), 'Peter Kamau', 'Sup3r-secret-pass!');

    $this->actingAs($user)->get(route('vendor.dashboard'))->assertOk();
    $this->get(route('vendor.units.index'))->assertOk();
});

test('auto approve gives landlords a property-owner workspace', function () {
    config(['access.auto_approve' => true]);

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData([
        'requested_type' => AccessRequest::TYPE_LANDLORD, 'organization' => 'Sunrise Apartments',
    ]));

    expect($request->status)->toBe(AccessRequestStatus::Approved)
        ->and($request->vendor->business_type)->toBe(BusinessType::Property);
});

test('the admin can approve someone as a team member of an existing workspace', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $workspace = Vendor::factory()->propertyOwner()->create();

    $request = app(SubmitAccessRequest::class)->handle(vendorRequestData(['email' => 'reader@sunrise.test']));

    $this->actingAs($admin);
    Livewire::test('admin.access-requests.index')
        ->call('openApprove', $request->id)
        ->set('role', 'meter_reader')
        ->call('approve')
        ->assertHasErrors('vendorId')
        ->set('vendorId', $workspace->id)
        ->call('approve')
        ->assertHasNoErrors();

    $request->refresh();
    $user = app(AcceptInvitation::class)->handle($request, inviteTokenFor($request), 'Mary Reader', 'Sup3r-secret-pass!');

    expect($user->hasRole('meter_reader'))->toBeTrue()
        ->and($user->vendor_id)->toBe($workspace->id)
        ->and(Vendor::count())->toBe(1);

    $this->actingAs($user)->get(route('vendor.readings.create'))->assertOk();
    $this->get(route('vendor.dashboard'))->assertForbidden();
});
