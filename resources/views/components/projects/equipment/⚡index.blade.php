<?php

use App\Models\ProjectEquipment;
use App\Models\ProjectSupplier;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Project equipment register — rigs, compressors, pumps, trucks — owned or
 * hired from a project supplier. Independent of Water Billing's accounting
 * assets. Shows where each machine is working now and what it has cost.
 */
new #[Layout('layouts.vendor'), Title('Equipment')] class extends Component {
    #[Url(as: 'q', history: true)] public string $search = '';
    #[Url(history: true)] public string $filter = '';

    public ?int $editingId = null;
    public string $name = '';
    public string $identifier = '';
    public string $type = '';
    public string $ownership = 'owned';
    public string $project_supplier_id = '';
    public string $default_rate = '0';
    public string $rate_unit = 'day';
    public string $status = 'available';
    public string $notes = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('projects.view'), 403);
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('projects.manage'), 403);
    }

    public function startAdd(): void
    {
        $this->authorizeManage();
        $this->reset('editingId', 'name', 'identifier', 'type', 'project_supplier_id', 'notes');
        $this->ownership = 'owned';
        $this->default_rate = '0';
        $this->rate_unit = 'day';
        $this->status = 'available';
        $this->resetValidation();
        Flux::modal('equipment')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorizeManage();
        $e = ProjectEquipment::findOrFail($id);
        $this->editingId = $e->id;
        foreach (['name', 'identifier', 'type', 'ownership', 'rate_unit', 'status', 'notes'] as $field) {
            $this->{$field} = (string) $e->{$field};
        }
        $this->project_supplier_id = (string) ($e->project_supplier_id ?? '');
        $this->default_rate = (string) $e->default_rate;
        $this->resetValidation();
        Flux::modal('equipment')->show();
    }

    public function save(): void
    {
        $this->authorizeManage();
        $vendorId = auth()->user()->vendor_id;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:60'],
            'ownership' => ['required', Rule::in(['owned', 'hired'])],
            'project_supplier_id' => ['nullable', 'required_if:ownership,hired', "exists:project_suppliers,id,vendor_id,{$vendorId}"],
            'default_rate' => ['required', 'numeric', 'min:0'],
            'rate_unit' => ['required', Rule::in(['day', 'hour'])],
            'status' => ['required', Rule::in(array_keys(ProjectEquipment::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['project_supplier_id.required_if' => 'Choose who the equipment is hired from.'], ['project_supplier_id' => 'hire company']);

        $data = [
            'name' => trim($this->name),
            'identifier' => $this->identifier ?: null,
            'type' => $this->type ?: null,
            'ownership' => $this->ownership,
            'project_supplier_id' => $this->ownership === 'hired' ? ($this->project_supplier_id ?: null) : null,
            'default_rate' => MoneyMath::str($this->default_rate),
            'rate_unit' => $this->rate_unit,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            ProjectEquipment::findOrFail($this->editingId)->update($data);
        } else {
            ProjectEquipment::create($data + ['vendor_id' => $vendorId]);
        }

        Flux::modal('equipment')->close();
        Flux::toast(variant: 'success', text: $this->editingId ? 'Equipment updated.' : 'Equipment added.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $e = ProjectEquipment::withCount('assignments')->findOrFail($id);

        if ($e->assignments_count) {
            $e->update(['status' => 'retired']);
            Flux::toast(variant: 'warning', heading: 'Equipment retired', text: 'It has been used on projects, so it is kept for the records.');

            return;
        }

        $e->delete();
        Flux::toast(variant: 'success', text: 'Equipment removed.');
    }

    public function with(): array
    {
        $equipment = ProjectEquipment::query()
            ->with(['supplier', 'assignments.project'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('identifier', 'like', "%{$this->search}%")
                ->orWhere('type', 'like', "%{$this->search}%")))
            ->when(in_array($this->filter, ['owned', 'hired'], true), fn ($q) => $q->where('ownership', $this->filter))
            ->when(array_key_exists($this->filter, ProjectEquipment::STATUSES), fn ($q) => $q->where('status', $this->filter))
            ->orderBy('name')
            ->get();

        return [
            'equipment' => $equipment->map(function (ProjectEquipment $e) {
                $live = $e->assignments->filter(fn ($a) => $a->end_date === null && $a->project?->isActive());

                return [
                    'model' => $e,
                    'on' => $live->map(fn ($a) => $a->project)->unique('id')->values(),
                    'cost' => MoneyMath::sumColumn($e->assignments->filter(fn ($a) => $a->project)->map(fn ($a) => ['c' => $a->cost()]), 'c'),
                ];
            }),
            'suppliers' => ProjectSupplier::query()->active()->orderBy('name')->get(['id', 'name']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-amber flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm"><flux:icon.wrench-screwdriver variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="lg">Equipment</flux:heading>
                <flux:text class="text-sm">Rigs, compressors, pumps and trucks for borehole projects — owned or hired. Separate from Water Billing's accounting assets.</flux:text>
            </div>
        </div>
        @can('projects.manage')
            <flux:button variant="primary" icon="plus" wire:click="startAdd">Add equipment</flux:button>
        @endcan
    </div>

    <div class="app-card grid gap-3 p-4 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search name, plate or type" class="sm:col-span-2" />
        <flux:select wire:model.live="filter">
            <flux:select.option value="">All equipment</flux:select.option>
            <flux:select.option value="owned">Owned</flux:select.option>
            <flux:select.option value="hired">Hired</flux:select.option>
            @foreach (ProjectEquipment::STATUSES as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
        </flux:select>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($equipment as $row)
            @php $e = $row['model']; @endphp
            <div wire:key="eq-{{ $e->id }}" class="app-card space-y-3 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $e->name }}</span>
                            <flux:badge size="sm" :color="$e->ownership === 'hired' ? 'violet' : 'sky'">{{ ucfirst($e->ownership) }}</flux:badge>
                            <flux:badge size="sm" :color="['available' => 'emerald', 'maintenance' => 'amber', 'retired' => 'zinc'][$e->status]">{{ ProjectEquipment::STATUSES[$e->status] }}</flux:badge>
                        </div>
                        <p class="text-xs text-zinc-500">{{ collect([$e->type, $e->identifier])->filter()->implode(' · ') ?: '—' }}</p>
                    </div>
                    @can('projects.manage')
                        <div class="flex shrink-0">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $e->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" class="!text-rose-600" wire:click="delete({{ $e->id }})"
                                wire:confirm="Remove {{ $e->name }}? Equipment used on projects is retired instead." />
                        </div>
                    @endcan
                </div>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-xs text-zinc-500">Default rate</dt><dd class="font-mono">{{ MoneyMath::format((string) $e->default_rate) }}/{{ $e->rate_unit }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">Cost to projects</dt><dd class="font-mono">{{ MoneyMath::format($row['cost']) }}</dd></div>
                    @if ($e->ownership === 'hired')
                        <div class="col-span-2"><dt class="text-xs text-zinc-500">Hired from</dt><dd>{{ $e->supplier?->name ?? '—' }}</dd></div>
                    @endif
                </dl>
                <div class="text-xs">
                    @if ($row['on']->isNotEmpty())
                        <span class="text-zinc-500">Working on:</span>
                        @foreach ($row['on'] as $p)
                            <a href="{{ route('projects.show', ['project' => $p, 'tab' => 'resources']) }}" wire:navigate class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $p->reference }}</a>@if (! $loop->last), @endif
                        @endforeach
                    @else
                        <span class="text-zinc-500">Not on any active project.</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="app-card p-10 text-center text-sm text-zinc-500 md:col-span-2 xl:col-span-3">No equipment yet. Add your rig, compressor and other machines to reuse them across projects.</div>
        @endforelse
    </div>

    <flux:modal name="equipment" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Edit equipment' : 'Add equipment' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" placeholder="e.g. Rotary drilling rig" class="sm:col-span-2" />
                <flux:input wire:model="type" label="Type" placeholder="Rig, compressor, pump, truck…" />
                <flux:input wire:model="identifier" label="Plate / serial number" />
                <flux:select wire:model.live="ownership" label="Ownership">
                    <flux:select.option value="owned">Owned</flux:select.option>
                    <flux:select.option value="hired">Hired</flux:select.option>
                </flux:select>
                @if ($ownership === 'hired')
                    <flux:select wire:model="project_supplier_id" label="Hired from">
                        <flux:select.option value="">— Choose a project supplier —</flux:select.option>
                        @foreach ($suppliers as $s) <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option> @endforeach
                    </flux:select>
                @else
                    <div></div>
                @endif
                <div class="grid grid-cols-2 gap-2">
                    <flux:input type="number" step="0.01" wire:model="default_rate" label="Default rate" />
                    <flux:select wire:model="rate_unit" label="Per">
                        <flux:select.option value="day">Day</flux:select.option>
                        <flux:select.option value="hour">Hour</flux:select.option>
                    </flux:select>
                </div>
                <flux:select wire:model="status" label="Status">
                    @foreach (ProjectEquipment::STATUSES as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
                </flux:select>
                <flux:textarea wire:model="notes" label="Notes" rows="2" class="sm:col-span-2" />
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
