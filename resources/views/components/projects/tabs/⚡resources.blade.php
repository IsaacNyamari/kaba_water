<?php

use App\Models\ProjectEquipment;
use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use App\Models\User;
use App\Services\Projects\ProjectAlerts;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

/*
 * Labour and equipment on the project. Team members and machines from the
 * project equipment register are linked (not re-created); casual workers and
 * one-off hires are entered by name. Cost = rate × units worked + extra cost.
 */
new class extends Component {
    public Project $project;

    public ?int $editingId = null;
    public string $kind = 'labour';
    public string $user_id = '';
    public string $equipment_id = '';
    public string $name = '';
    public string $role = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $rate = '0';
    public string $rate_unit = 'day';
    public string $units_worked = '0';
    public string $extra_cost = '0';
    public string $downtime_units = '0';
    public string $notes = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function startAdd(string $kind): void
    {
        $this->authorize('update', $this->project);
        $this->reset('editingId', 'user_id', 'equipment_id', 'name', 'role', 'end_date', 'notes');
        $this->kind = $kind;
        $this->rate = $this->units_worked = $this->extra_cost = $this->downtime_units = '0';
        $this->rate_unit = 'day';
        $this->start_date = today()->toDateString();
        $this->resetValidation();
        Flux::modal('resource')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('update', $this->project);
        $a = $this->project->assignments()->findOrFail($id);
        $this->editingId = $a->id;
        foreach (['kind', 'name', 'role', 'rate_unit', 'notes'] as $f) {
            $this->{$f} = (string) ($a->{$f} ?? '');
        }
        foreach (['user_id', 'equipment_id', 'rate', 'units_worked', 'extra_cost', 'downtime_units'] as $f) {
            $this->{$f} = (string) ($a->{$f} ?? '');
        }
        $this->start_date = $a->start_date?->toDateString() ?? '';
        $this->end_date = $a->end_date?->toDateString() ?? '';
        $this->resetValidation();
        Flux::modal('resource')->show();
    }

    public function updatedUserId(): void
    {
        if ($this->user_id && $user = User::query()->where('vendor_id', $this->project->vendor_id)->find($this->user_id)) {
            $this->name = $user->name;
        }
    }

    /** Picking a machine fills in its name, type and default hire rate. */
    public function updatedEquipmentId(): void
    {
        if ($this->equipment_id && $machine = ProjectEquipment::query()->find($this->equipment_id)) {
            $this->name = $machine->label();
            $this->role = (string) $machine->type;
            if ((float) $machine->default_rate > 0) {
                $this->rate = (string) $machine->default_rate;
                $this->rate_unit = $machine->rate_unit;
            }
        }
    }

    public function save(): void
    {
        $this->authorize('update', $this->project);
        $data = $this->validate([
            'kind' => ['required', Rule::in(['labour', 'equipment'])],
            'user_id' => ['nullable', 'exists:users,id,vendor_id,'.$this->project->vendor_id],
            'equipment_id' => ['nullable', 'exists:project_equipment,id,vendor_id,'.$this->project->vendor_id],
            'name' => ['required', 'string', 'max:200'],
            'role' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rate' => ['required', 'numeric', 'min:0'],
            'rate_unit' => ['required', Rule::in(['day', 'hour'])],
            'units_worked' => ['required', 'numeric', 'min:0', 'max:100000'],
            'extra_cost' => ['required', 'numeric', 'min:0'],
            'downtime_units' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data = collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['rate'] = MoneyMath::str($this->rate);
        $data['extra_cost'] = MoneyMath::str($this->extra_cost);
        if ($data['kind'] === 'labour') {
            $data['equipment_id'] = null;
        } else {
            $data['user_id'] = null;
        }

        if ($this->editingId) {
            $a = $this->project->assignments()->findOrFail($this->editingId);
            $previousUser = $a->user_id;
            $a->update($data);
            $this->project->log('resource', "Updated {$a->kind}: {$a->name} — cost ".MoneyMath::format($a->cost()), ['assignment_id' => $a->id] + $a->getChanges());
        } else {
            $previousUser = null;
            $a = $this->project->assignments()->create($data);
            $this->project->log('resource', "Added {$a->kind}: {$a->name}", ['assignment_id' => $a->id]);
        }

        if ($a->user_id && $a->user_id !== $previousUser) {
            ProjectAlerts::notifyUser($a->user, 'Assigned to a project', "{$this->project->reference} · {$this->project->name}".($a->role ? " as {$a->role}" : ''), $this->project, 'overview');
        }

        app(ProjectAlerts::class)->checkBudget($this->project);

        Flux::modal('resource')->close();
        Flux::toast(variant: 'success', text: 'Saved.');
        $this->dispatch('project-changed');
    }

    public function with(): array
    {
        $assignments = $this->project->assignments()->with(['user', 'equipment'])->orderBy('kind')->orderBy('name')->get();

        // How many other active projects each linked person / machine is on.
        $elsewhere = ProjectTeamAssignment::query()
            ->whereHas('project', fn ($q) => $q->active()->where('vendor_id', $this->project->vendor_id)->whereKeyNot($this->project->id))
            ->whereNull('end_date')
            ->get(['user_id', 'equipment_id']);

        return [
            'labour' => $assignments->where('kind', 'labour'),
            'equipment' => $assignments->where('kind', 'equipment'),
            'busyUsers' => $elsewhere->whereNotNull('user_id')->countBy('user_id'),
            'busyEquipment' => $elsewhere->whereNotNull('equipment_id')->countBy('equipment_id'),
            'team' => User::query()->where('vendor_id', $this->project->vendor_id)->whereHas('roles')->orderBy('name')->get(['id', 'name']),
            'machines' => ProjectEquipment::query()->where('vendor_id', $this->project->vendor_id)->where('status', '!=', 'retired')->orderBy('name')->get(['id', 'name', 'identifier', 'ownership']),
        ];
    }
};
?>

<div class="space-y-6">
    @foreach (['labour' => ['Labour', 'user-group', $labour], 'equipment' => ['Equipment', 'truck', $equipment]] as $kindKey => [$heading, $icon, $rows])
        <div class="space-y-3" wire:key="section-{{ $kindKey }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <flux:icon :name="$icon" class="size-5 text-zinc-400" />
                    <flux:heading size="lg">{{ $heading }}</flux:heading>
                    <span class="font-mono text-sm text-zinc-500">{{ MoneyMath::format(MoneyMath::sumColumn($rows->map(fn ($r) => ['c' => $r->cost()]), 'c')) }}</span>
                </div>
                @can('update', $project)
                    <flux:button size="sm" icon="plus" wire:click="startAdd('{{ $kindKey }}')">Add {{ strtolower($heading) }}</flux:button>
                @endcan
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ $kindKey === 'labour' ? 'Worker' : 'Equipment' }}</flux:table.column>
                    <flux:table.column>Dates</flux:table.column>
                    <flux:table.column align="end">Rate</flux:table.column>
                    <flux:table.column align="end">Worked</flux:table.column>
                    @if ($kindKey === 'equipment') <flux:table.column align="end">Downtime</flux:table.column> @endif
                    <flux:table.column align="end">Extra</flux:table.column>
                    <flux:table.column align="end">Cost</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($rows as $a)
                        @php $busy = $a->user_id ? ($busyUsers[$a->user_id] ?? 0) : ($a->equipment_id ? ($busyEquipment[$a->equipment_id] ?? 0) : 0); @endphp
                        <flux:table.row :key="$a->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $a->name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $a->role ?: ($a->user ? 'Team member' : ($a->equipment ? ($a->equipment->ownership === 'hired' ? 'Hired (register)' : 'Own equipment') : ($kindKey === 'labour' ? 'Casual' : 'Hired'))) }}
                                    @if ($busy) · <span class="text-amber-600">also on {{ $busy }} other project(s)</span> @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-xs">{{ $a->start_date?->format('d M') ?? '—' }} → {{ $a->end_date?->format('d M') ?? 'ongoing' }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format((string) $a->rate) }}/{{ $a->rate_unit }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-mono">{{ (float) $a->units_worked }} {{ $a->rate_unit }}s</flux:table.cell>
                            @if ($kindKey === 'equipment') <flux:table.cell align="end" class="font-mono">{{ (float) $a->downtime_units }}</flux:table.cell> @endif
                            <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format((string) $a->extra_cost) }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-mono font-semibold">{{ MoneyMath::format($a->cost()) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $project)
                                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $a->id }})" />
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="8" class="text-center text-zinc-500">None yet.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endforeach

    <flux:modal name="resource" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit' : 'Add' }} {{ $kind === 'labour' ? 'labour' : 'equipment' }}</flux:heading>
                <flux:subheading>Update the days or hours worked as the job goes on. Set an end date when the timesheet is final.</flux:subheading>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($kind === 'labour')
                    <flux:select wire:model.live="user_id" label="Team member">
                        <flux:select.option value="">— Casual / not on the team —</flux:select.option>
                        @foreach ($team as $u) <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option> @endforeach
                    </flux:select>
                @else
                    <flux:select wire:model.live="equipment_id" label="From the equipment register">
                        <flux:select.option value="">— One-off hire (not in the register) —</flux:select.option>
                        @foreach ($machines as $machine) <flux:select.option value="{{ $machine->id }}">{{ $machine->label() }}{{ $machine->ownership === 'hired' ? ' · hired' : '' }}</flux:select.option> @endforeach
                    </flux:select>
                @endif
                <flux:input wire:model="name" label="Name" />
                <flux:input wire:model="role" :label="$kind === 'labour' ? 'Role' : 'Type'" :placeholder="$kind === 'labour' ? 'Driller, helper, plumber…' : 'Rig, compressor, truck…'" />
                <div class="grid grid-cols-2 gap-2">
                    <flux:input type="date" wire:model="start_date" label="From" />
                    <flux:input type="date" wire:model="end_date" label="To" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <flux:input type="number" step="0.01" wire:model="rate" label="Rate" />
                    <flux:select wire:model="rate_unit" label="Per">
                        <flux:select.option value="day">Day</flux:select.option>
                        <flux:select.option value="hour">Hour</flux:select.option>
                    </flux:select>
                </div>
                <flux:input type="number" step="0.01" wire:model="units_worked" label="Days / hours worked" />
                <flux:input type="number" step="0.01" wire:model="extra_cost" :label="$kind === 'labour' ? 'Overtime & allowances' : 'Fuel & maintenance'" />
                @if ($kind === 'equipment')
                    <flux:input type="number" step="0.01" wire:model="downtime_units" label="Downtime (days / hours)" />
                @endif
            </div>
            <flux:input wire:model="notes" label="Notes" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
