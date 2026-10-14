<?php

use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectAlerts;
use App\Support\Money\MoneyMath;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Register or edit a borehole project. A new project gets the default
 * milestone sequence (config/projects.php) spread across its schedule.
 * After the draft stage the budget changes only through a logged revision.
 */
new #[Layout('layouts.vendor')] class extends Component {
    public ?Project $project = null;

    public string $name = '';
    public string $description = '';
    public string $county = '';
    public string $sub_county = '';
    public string $latitude = '';
    public string $longitude = '';
    public string $site_details = '';
    public string $start_date = '';
    public string $expected_completion_date = '';
    public string $project_manager_id = '';
    public string $budget = '';
    public string $contingency = '0';
    public string $estimated_cost_to_complete = '';
    public string $planned_depth_m = '';
    public string $diameter_mm = '';
    public string $casing_requirements = '';
    public string $pump_capacity = '';
    public string $scope = '';
    public string $deliverables = '';
    public string $special_requirements = '';

    public function mount(?Project $project = null): void
    {
        if ($project && $project->exists) {
            $this->authorize('update', $project);
            $this->project = $project;
            foreach (['name', 'description', 'county', 'sub_county', 'site_details', 'casing_requirements', 'pump_capacity', 'scope', 'deliverables', 'special_requirements'] as $field) {
                $this->{$field} = (string) ($project->{$field} ?? '');
            }
            $this->latitude = (string) ($project->latitude ?? '');
            $this->longitude = (string) ($project->longitude ?? '');
            $this->start_date = $project->start_date?->toDateString() ?? '';
            $this->expected_completion_date = $project->expected_completion_date?->toDateString() ?? '';
            $this->project_manager_id = (string) ($project->project_manager_id ?? '');
            $this->budget = (string) $project->current_budget;
            $this->contingency = (string) $project->contingency;
            $this->estimated_cost_to_complete = (string) ($project->estimated_cost_to_complete ?? '');
            $this->planned_depth_m = (string) ($project->planned_depth_m ?? '');
            $this->diameter_mm = (string) ($project->diameter_mm ?? '');
        } else {
            $this->authorize('create', Project::class);
            $this->start_date = today()->toDateString();
            $this->expected_completion_date = today()->addDays(60)->toDateString();
        }
    }

    public function title(): string
    {
        return $this->project ? 'Edit '.$this->project->reference : 'New project';
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'county' => ['nullable', 'string', 'max:100'],
            'sub_county' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_details' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'expected_completion_date' => ['required', 'date', 'after_or_equal:start_date'],
            'project_manager_id' => ['nullable', "exists:users,id,vendor_id,{$vendorId}"],
            'budget' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'contingency' => ['required', 'numeric', 'min:0', 'lte:budget'],
            'estimated_cost_to_complete' => ['nullable', 'numeric', 'min:0'],
            'planned_depth_m' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'diameter_mm' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'casing_requirements' => ['nullable', 'string', 'max:255'],
            'pump_capacity' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'deliverables' => ['nullable', 'string', 'max:5000'],
            'special_requirements' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function save(): void
    {
        $this->authorize($this->project ? 'update' : 'create', $this->project ?? Project::class);
        $this->validate();

        $payload = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'county' => $this->county ?: null,
            'sub_county' => $this->sub_county ?: null,
            'latitude' => $this->latitude !== '' ? $this->latitude : null,
            'longitude' => $this->longitude !== '' ? $this->longitude : null,
            'site_details' => $this->site_details ?: null,
            'start_date' => $this->start_date,
            'expected_completion_date' => $this->expected_completion_date,
            'project_manager_id' => $this->project_manager_id ?: null,
            'contingency' => MoneyMath::str($this->contingency),
            'estimated_cost_to_complete' => $this->estimated_cost_to_complete !== '' ? MoneyMath::str($this->estimated_cost_to_complete) : null,
            'planned_depth_m' => $this->planned_depth_m !== '' ? $this->planned_depth_m : null,
            'diameter_mm' => $this->diameter_mm !== '' ? (int) $this->diameter_mm : null,
            'casing_requirements' => $this->casing_requirements ?: null,
            'pump_capacity' => $this->pump_capacity ?: null,
            'scope' => $this->scope ?: null,
            'deliverables' => $this->deliverables ?: null,
            'special_requirements' => $this->special_requirements ?: null,
        ];

        if ($this->project) {
            $project = $this->project;
            $previousManager = $project->project_manager_id;

            DB::transaction(function () use ($project, $payload) {
                // Budget is set freely while drafting; afterwards it changes only by a logged revision.
                if ($project->status === 'draft') {
                    $payload['original_budget'] = $payload['current_budget'] = MoneyMath::str($this->budget);
                }
                $project->update($payload);
                $changed = array_keys($project->getChanges());
                $project->log('updated', 'Project details updated', ['fields' => array_values(array_diff($changed, ['updated_at']))]);
            });

            session()->flash('status', 'Project updated.');
        } else {
            $vendorId = auth()->user()->vendor_id;
            $previousManager = null;

            $project = DB::transaction(function () use ($payload, $vendorId) {
                $project = Project::create($payload + [
                    'vendor_id' => $vendorId,
                    'reference' => Project::nextReference($vendorId),
                    'status' => 'draft',
                    'original_budget' => MoneyMath::str($this->budget),
                    'current_budget' => MoneyMath::str($this->budget),
                ]);

                // Default milestones, planned evenly across the schedule.
                $defaults = config('projects.milestones');
                $start = $project->start_date;
                $span = max(1, $start->diffInDays($project->expected_completion_date));
                foreach ($defaults as $i => [$name, $weight]) {
                    $project->milestones()->create([
                        'name' => $name,
                        'weight' => $weight,
                        'sort_order' => $i + 1,
                        'planned_date' => $start->addDays((int) round($span * ($i + 1) / count($defaults))),
                    ]);
                }

                $project->log('created', "Project {$project->reference} registered with a budget of ".MoneyMath::format((string) $project->original_budget));

                return $project;
            });

            session()->flash('status', "Project {$project->reference} created.");
        }

        if ($project->project_manager_id && $project->project_manager_id !== $previousManager) {
            ProjectAlerts::notifyUser($project->manager, 'You are project manager', "{$project->reference} · {$project->name}", $project, 'overview');
        }

        $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
    }

    public function with(): array
    {
        return [
            'managers' => User::query()->where('vendor_id', auth()->user()->vendor_id)
                ->permission('projects.manage')->orderBy('name')->get(['id', 'name']),
            'budgetLocked' => $this->project && $this->project->status !== 'draft',
        ];
    }
};
?>

<div class="p-4 lg:p-6 max-w-4xl space-y-6">
    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-5">
            <div class="flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-amber-500 text-white"><flux:icon.briefcase variant="mini" class="size-5" /></span>
                <div>
                    <flux:heading size="lg">{{ $project ? 'Edit '.$project->reference : 'Register a borehole project' }}</flux:heading>
                    <flux:subheading>{{ $project ? 'Changes are recorded in the activity log.' : 'A reference number and the default milestones are created for you.' }}</flux:subheading>
                </div>
            </div>

            <flux:input wire:model="name" label="Project name" placeholder="e.g. Kitengela community borehole" />
            <flux:textarea wire:model="description" label="Description" rows="2" />

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input type="date" wire:model="start_date" label="Start date" />
                <flux:input type="date" wire:model="expected_completion_date" label="Expected completion" />
                <flux:select wire:model="project_manager_id" label="Project manager" placeholder="— Choose —">
                    <flux:select.option value="">— None yet —</flux:select.option>
                    @foreach ($managers as $m)
                        <flux:select.option value="{{ $m->id }}">{{ $m->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="sm">Location</flux:heading>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="county" label="County" />
                <flux:input wire:model="sub_county" label="Sub-county" />
                <flux:input wire:model="latitude" label="GPS latitude" placeholder="-1.2921" />
                <flux:input wire:model="longitude" label="GPS longitude" placeholder="36.8219" />
            </div>
            <flux:textarea wire:model="site_details" label="Site details" rows="2" placeholder="Access road, landmarks, owner contact on site…" />
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="sm">Budget (internal cost)</flux:heading>
            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input type="number" step="0.01" min="0" wire:model="budget" label="Approved budget" :disabled="$budgetLocked"
                    :description="$budgetLocked ? 'Use Revise budget on the Financials tab.' : null" />
                <flux:input type="number" step="0.01" min="0" wire:model="contingency" label="Of which contingency" />
                <flux:input type="number" step="0.01" min="0" wire:model="estimated_cost_to_complete" label="Estimate to complete (optional)"
                    description="Blank = open tasks + open orders." />
            </div>
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="sm">Borehole specification & scope</flux:heading>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input type="number" step="0.01" wire:model="planned_depth_m" label="Planned depth (m)" />
                <flux:input type="number" wire:model="diameter_mm" label="Diameter (mm)" />
                <flux:input wire:model="casing_requirements" label="Casing requirements" placeholder="e.g. 6&quot; uPVC, 120 m" />
                <flux:input wire:model="pump_capacity" label="Pump capacity" placeholder="e.g. 5 m³/h submersible" />
            </div>
            <flux:textarea wire:model="scope" label="Scope" rows="2" />
            <flux:textarea wire:model="deliverables" label="Deliverables" rows="2" />
            <flux:textarea wire:model="special_requirements" label="Special requirements" rows="2" />
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" :href="$project ? route('projects.show', $project) : route('projects.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $project ? 'Save changes' : 'Create project' }}</flux:button>
        </div>
    </form>
</div>
