<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DemoProjectSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->vendorA = Vendor::factory()->create();
    $this->vendorB = Vendor::factory()->create();

    $this->ownerA = User::factory()->create(['vendor_id' => $this->vendorA->id]);
    $this->ownerA->assignRole('vendor');
    $this->ownerB = User::factory()->create(['vendor_id' => $this->vendorB->id]);
    $this->ownerB->assignRole('vendor');

    $this->projectA = Project::create([
        'vendor_id' => $this->vendorA->id, 'reference' => 'BH-2026-001', 'name' => 'Vendor A secret borehole', 'status' => 'drilling',
        'original_budget' => '1000.00', 'current_budget' => '1000.00',
    ]);
});

test('a vendor cannot open, list or change another vendor\'s project', function () {
    $this->actingAs($this->ownerB);

    $this->get(route('projects.show', $this->projectA))->assertNotFound();
    $this->get(route('projects.edit', $this->projectA))->assertNotFound();
    $this->get(route('projects.index'))->assertOk()->assertDontSee('Vendor A secret borehole');

    // Even when handed the model directly, the policy refuses.
    Livewire::test('projects.tabs.expenses', ['project' => $this->projectA])->assertForbidden();
    expect($this->ownerB->can('view', $this->projectA))->toBeFalse();
});

test('the owner sees their own project', function () {
    $this->actingAs($this->ownerA)
        ->get(route('projects.show', $this->projectA))
        ->assertOk()
        ->assertSee('Vendor A secret borehole');
});

test('every project tab renders with real data', function (string $tab) {
    $demo = Vendor::factory()->create(['slug' => 'demo-water']);
    $owner = User::factory()->create(['vendor_id' => $demo->id, 'email' => 'vendor@swb.test']);
    $owner->assignRole('vendor');
    $this->seed(DemoProjectSeeder::class);
    $project = Project::withoutGlobalScope('vendor')->where('vendor_id', $demo->id)->sole();

    $this->actingAs($owner)
        ->get(route('projects.show', ['project' => $project, 'tab' => $tab]))
        ->assertOk()
        ->assertSee('Kitengela community borehole');
})->with(['overview', 'milestones', 'progress', 'tasks', 'expenses', 'materials', 'resources', 'financials', 'technical', 'closure', 'activity']);

test('roles without project access are kept out', function () {
    $technician = User::factory()->create(['vendor_id' => $this->vendorA->id]);
    $technician->assignRole('technician');

    $this->actingAs($technician)->get(route('projects.index'))->assertForbidden();
});

test('a site engineer can submit expenses but not approve them', function () {
    $engineer = User::factory()->create(['vendor_id' => $this->vendorA->id]);
    $engineer->assignRole('site_engineer');
    $this->actingAs($engineer);

    Livewire::test('projects.tabs.expenses', ['project' => $this->projectA])
        ->set('category', 'fuel_transport')
        ->set('description', 'Diesel for the rig')
        ->set('expense_date', today()->toDateString())
        ->set('amount', '4500')
        ->set('payee_name', 'Shell Athi River')
        ->call('submit')
        ->assertHasNoErrors();

    $expense = $this->projectA->expenses()->sole();
    expect($expense->status)->toBe('submitted')->and($expense->submitted_by)->toBe($engineer->id);

    Livewire::test('projects.tabs.expenses', ['project' => $this->projectA])
        ->call('approve', $expense->id)
        ->assertForbidden();
});

test('creating a project gives it a reference and the default milestones', function () {
    $this->actingAs($this->ownerA);

    Livewire::test('projects.form')
        ->set('name', 'Kitengela borehole')
        ->set('budget', '850000')
        ->set('contingency', '50000')
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::query()->where('name', 'Kitengela borehole')->sole();
    // BH-YYYY-001 is already taken by the project from beforeEach.
    expect($project->reference)->toBe('BH-'.now()->year.'-002')
        ->and($project->vendor_id)->toBe($this->vendorA->id)
        ->and((string) $project->original_budget)->toBe('850000.00')
        ->and($project->milestones()->count())->toBe(count(config('projects.milestones')))
        ->and($project->activities()->where('event', 'created')->exists())->toBeTrue();
});

test('users with both modules land on the module portal and can switch', function () {
    $this->post(route('login.store'), ['email' => $this->ownerA->email, 'password' => 'password'])
        ->assertRedirect(route('modules'));

    $this->get(route('modules'))->assertOk()->assertSee('Water Billing')->assertSee('Project Management');
    $this->get(route('projects.index'))->assertOk()->assertSee('data-test="module-switcher"', false);
    $this->get(route('vendor.dashboard'))->assertOk()->assertSee('data-test="module-switcher"', false);
});

test('a project manager goes straight to projects', function () {
    $pm = User::factory()->create(['vendor_id' => $this->vendorA->id]);
    $pm->assignRole('project_manager');

    $this->post(route('login.store'), ['email' => $pm->email, 'password' => 'password'])
        ->assertRedirect(route('projects.index'));
});
