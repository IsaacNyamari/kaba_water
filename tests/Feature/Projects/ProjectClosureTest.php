<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Projects\MaterialLedger;
use App\Services\Projects\ProjectClosure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->vendor = Vendor::factory()->create();
    $this->owner = User::factory()->create(['vendor_id' => $this->vendor->id]);
    $this->owner->assignRole('vendor');
    $this->actingAs($this->owner);

    $this->project = Project::create([
        'vendor_id' => $this->vendor->id, 'reference' => 'BH-2026-001', 'name' => 'Closing borehole', 'status' => 'commissioning',
        'original_budget' => '50000.00', 'current_budget' => '50000.00',
    ]);
    $this->project->milestones()->create(['name' => 'Commissioning', 'weight' => 1, 'sort_order' => 1]);
    $this->casing = $this->project->materials()->create(['name' => 'Casing', 'unit' => 'pcs']);
    app(MaterialLedger::class)->record($this->casing, ['type' => 'received', 'quantity' => '4', 'unit_cost' => '1000', 'movement_date' => today()->toDateString()]);
});

function satisfyClosureChecklist(Project $project, $casing): void
{
    $project->milestones()->update(['completed_at' => now()]);
    app(MaterialLedger::class)->record($casing, ['type' => 'consumed', 'quantity' => '3', 'movement_date' => today()->toDateString()]);
    app(MaterialLedger::class)->record($casing, ['type' => 'returned', 'quantity' => '1', 'movement_date' => today()->toDateString()]);
    $project->update([
        'technical' => ['final_depth_m' => '120', 'yield_m3_per_hour' => '4.5', 'water_quality_result' => 'Potable',
            'final_inspection_date' => today()->toDateString(), 'final_inspection_result' => 'Passed'],
        'attachments' => [['id' => 'x', 'type' => 'completion_certificate', 'name' => 'cert.pdf', 'path' => 'cert.pdf']],
    ]);
}

test('a project cannot close until every checklist item passes', function () {
    $closure = app(ProjectClosure::class);

    $failing = collect($closure->checklist($this->project))->where('passed', false)->pluck('key')->all();
    expect($failing)->toContain('milestones', 'technical', 'materials', 'handover')
        ->and(fn () => $closure->close($this->project))->toThrow(ValidationException::class);

    expect($this->project->fresh()->status)->toBe('commissioning');
});

test('closing freezes the final statement and makes the project read-only', function () {
    satisfyClosureChecklist($this->project, $this->casing);

    app(ProjectClosure::class)->close($this->project->fresh(), 'Handed over to the community committee');

    $project = $this->project->fresh();
    expect($project->status)->toBe('completed')
        ->and($project->closed_by)->toBe($this->owner->id)
        ->and($project->final_statement['total_final_cost'])->toBe('3000.00')
        ->and($project->final_statement['variance'])->toBe('47000.00')
        ->and($this->owner->can('update', $project))->toBeFalse()
        ->and($this->owner->can('submitExpense', $project))->toBeFalse()
        ->and($project->activities()->where('event', 'closed')->exists())->toBeTrue();
});

test('reopening needs the close permission and is logged with the previous statement', function () {
    satisfyClosureChecklist($this->project, $this->casing);
    app(ProjectClosure::class)->close($this->project->fresh());

    $engineer = User::factory()->create(['vendor_id' => $this->vendor->id]);
    $engineer->assignRole('site_engineer');

    $this->actingAs($engineer);
    Livewire::test('projects.tabs.closure', ['project' => $this->project->fresh()])
        ->set('reopenReason', 'Pump fault')
        ->call('reopen')
        ->assertForbidden();

    $this->actingAs($this->owner);
    Livewire::test('projects.tabs.closure', ['project' => $this->project->fresh()])
        ->set('reopenReason', 'Pump fault')
        ->call('reopen')
        ->assertHasNoErrors();

    $project = $this->project->fresh();
    $log = $project->activities()->where('event', 'reopened')->first();
    expect($project->status)->toBe('commissioning')
        ->and($project->closed_at)->toBeNull()
        ->and($log->properties['reason'])->toBe('Pump fault')
        ->and($log->properties['previous_statement']['total_final_cost'])->toBe('3000.00');
});
