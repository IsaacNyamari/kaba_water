<?php

use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\ProjectMaterialMovement;
use App\Models\ProjectSupplier;
use App\Services\Projects\MaterialLedger;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Project $project;

    // Material
    public ?int $editingId = null;
    public string $name = '';
    public string $sku = '';
    public string $unit = 'pcs';
    public string $reorder_level = '0';
    public string $storage_location = '';

    // Movement
    public ?int $movingId = null;
    public string $type = 'received';
    public string $quantity = '';
    public string $unit_cost = '';
    public string $supplier_id = '';
    public string $reference = '';
    public string $movement_date = '';
    public string $notes = '';
    public $attachment = null;

    public ?int $historyId = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function startAdd(): void
    {
        $this->authorize('manageMaterials', $this->project);
        $this->reset('editingId', 'name', 'sku', 'storage_location');
        $this->unit = 'pcs';
        $this->reorder_level = '0';
        $this->resetValidation();
        Flux::modal('material')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('manageMaterials', $this->project);
        $m = $this->project->materials()->findOrFail($id);
        $this->editingId = $m->id;
        $this->name = $m->name;
        $this->sku = (string) $m->sku;
        $this->unit = $m->unit;
        $this->reorder_level = (string) $m->reorder_level;
        $this->storage_location = (string) $m->storage_location;
        $this->resetValidation();
        Flux::modal('material')->show();
    }

    public function saveMaterial(): void
    {
        $this->authorize('manageMaterials', $this->project);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:200', Rule::unique('project_materials', 'name')->where('project_id', $this->project->id)->ignore($this->editingId)],
            'sku' => ['nullable', 'string', 'max:60'],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->editingId) {
            $this->project->materials()->findOrFail($this->editingId)->update($data);
        } else {
            $m = $this->project->materials()->create($data);
            $this->project->log('material', "Material added: {$m->name} ({$m->unit})", ['material_id' => $m->id]);
        }

        Flux::modal('material')->close();
        Flux::toast(variant: 'success', text: 'Material saved.');
    }

    public function startMovement(int $id, string $type = 'received'): void
    {
        $this->authorize('manageMaterials', $this->project);
        $this->movingId = $this->project->materials()->findOrFail($id)->id;
        $this->reset('quantity', 'unit_cost', 'supplier_id', 'reference', 'notes', 'attachment');
        $this->type = $type;
        $this->movement_date = today()->toDateString();
        $this->resetValidation();
        Flux::modal('movement')->show();
    }

    public function saveMovement(): void
    {
        $this->authorize('manageMaterials', $this->project);
        $material = $this->project->materials()->findOrFail($this->movingId);

        $data = $this->validate([
            'type' => ['required', Rule::in(array_keys(ProjectMaterialMovement::TYPES))],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'unit_cost' => [Rule::requiredIf(in_array($this->type, ['received', 'ordered'], true)), 'nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:project_suppliers,id,vendor_id,'.$this->project->vendor_id],
            'reference' => ['nullable', 'string', 'max:100'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $data = collect($data)->except('attachment')->map(fn ($v) => $v === '' ? null : $v)->all();
        if ($this->attachment) {
            $data['attachment_path'] = $this->attachment->store("projects/{$this->project->id}/materials", 'public');
        }

        app(MaterialLedger::class)->record($material, $data);

        Flux::modal('movement')->close();
        Flux::toast(variant: 'success', heading: ProjectMaterialMovement::TYPES[$this->type], text: "{$this->quantity} {$material->unit} of {$material->name}");
        $this->dispatch('project-changed');
    }

    public function showHistory(int $id): void
    {
        $this->historyId = $this->historyId === $id ? null : $id;
    }

    public function with(): array
    {
        $ledger = app(MaterialLedger::class);
        $materials = $this->project->materials()->with('movements.recorder', 'movements.supplier')->get();

        return [
            'materials' => $materials,
            'summaries' => $materials->mapWithKeys(fn ($m) => [$m->id => $ledger->summary($m)]),
            'suppliers' => ProjectSupplier::query()->where('vendor_id', $this->project->vendor_id)->active()->orderBy('name')->get(['id', 'name']),
        ];
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">Materials</flux:heading>
            <flux:text class="text-sm">Buying is not cost — materials become project cost when consumed or wasted (weighted-average cost). Stock left on site is reconciled at closure.</flux:text>
        </div>
        @can('manageMaterials', $project)
            <flux:button variant="primary" icon="plus" wire:click="startAdd">Add material</flux:button>
        @endcan
    </div>

    @php $low = $summaries->filter(fn ($s) => $s['low_stock']); @endphp
    @if ($low->isNotEmpty())
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.text>Low stock: {{ $materials->whereIn('id', $low->keys())->pluck('name')->implode(', ') }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Material</flux:table.column>
            <flux:table.column align="end">Ordered</flux:table.column>
            <flux:table.column align="end">Received</flux:table.column>
            <flux:table.column align="end">Consumed</flux:table.column>
            <flux:table.column align="end">Returned</flux:table.column>
            <flux:table.column align="end">Wasted</flux:table.column>
            <flux:table.column align="end">On hand</flux:table.column>
            <flux:table.column align="end">Avg cost</flux:table.column>
            <flux:table.column align="end">Cost</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($materials as $m)
                @php $s = $summaries[$m->id]; @endphp
                <flux:table.row :key="$m->id">
                    <flux:table.cell>
                        <button type="button" wire:click="showHistory({{ $m->id }})" class="text-start">
                            <div class="font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $m->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $m->sku ? $m->sku.' · ' : '' }}{{ $m->unit }}{{ $m->storage_location ? ' · '.$m->storage_location : '' }}</div>
                        </button>
                        @if ($s['low_stock']) <flux:badge size="sm" color="amber">Low stock</flux:badge> @endif
                    </flux:table.cell>
                    @foreach (['ordered', 'received', 'consumed', 'returned', 'wasted', 'on_hand'] as $col)
                        <flux:table.cell align="end" class="font-mono {{ $col === 'wasted' && (float) $s[$col] > 0 ? 'text-rose-600' : '' }}">{{ (float) $s[$col] }}</flux:table.cell>
                    @endforeach
                    <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format($s['avg_cost']) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format($s['cost']) }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('manageMaterials', $project)
                            <flux:dropdown align="end">
                                <flux:button size="xs" icon="plus" icon-trailing="chevron-down">Record</flux:button>
                                <flux:menu>
                                    @foreach (ProjectMaterialMovement::TYPES as $key => $label)
                                        <flux:menu.item wire:click="startMovement({{ $m->id }}, '{{ $key }}')">{{ $label }}</flux:menu.item>
                                    @endforeach
                                    <flux:menu.separator />
                                    <flux:menu.item icon="pencil-square" wire:click="startEdit({{ $m->id }})">Edit material</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
                @if ($historyId === $m->id)
                    <flux:table.row :key="'history-'.$m->id">
                        <flux:table.cell colspan="10" class="bg-zinc-50 dark:bg-white/5">
                            <div class="space-y-1 text-xs">
                                @forelse ($m->movements as $mv)
                                    <div class="flex flex-wrap gap-x-3">
                                        <span class="font-mono">{{ $mv->movement_date->format('d M Y') }}</span>
                                        <span class="font-medium">{{ ProjectMaterialMovement::TYPES[$mv->type] }}</span>
                                        <span class="font-mono">{{ (float) $mv->quantity }} {{ $m->unit }}</span>
                                        @if ($mv->unit_cost !== null) <span>@ {{ MoneyMath::format((string) $mv->unit_cost) }}</span> @endif
                                        @if ($mv->supplier) <span>{{ $mv->supplier->name }}</span> @endif
                                        @if ($mv->reference) <span>Ref {{ $mv->reference }}</span> @endif
                                        <span class="text-zinc-500">{{ $mv->recorder?->name }}</span>
                                        @if ($mv->notes) <span class="text-zinc-500">— {{ $mv->notes }}</span> @endif
                                        @if ($mv->attachment_path) <a href="{{ Storage::disk('public')->url($mv->attachment_path) }}" target="_blank" class="text-brand-700 hover:underline dark:text-brand-300">document</a> @endif
                                    </div>
                                @empty
                                    <span class="text-zinc-500">No movements yet.</span>
                                @endforelse
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endif
            @empty
                <flux:table.row><flux:table.cell colspan="10" class="text-center text-zinc-500">No materials yet.</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="material" class="w-full max-w-lg">
        <form wire:submit="saveMaterial" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Edit material' : 'Add material' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" placeholder="e.g. 6&quot; uPVC casing" class="sm:col-span-2" />
                <flux:input wire:model="sku" label="SKU / item code" />
                <flux:input wire:model="unit" label="Unit" placeholder="pcs, m, bags, kg" />
                <flux:input type="number" step="0.001" wire:model="reorder_level" label="Low-stock level" />
                <flux:input wire:model="storage_location" label="Storage location" />
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="movement" class="w-full max-w-lg">
        <form wire:submit="saveMovement" class="space-y-5">
            <flux:heading size="lg">Record material movement</flux:heading>
            <flux:select wire:model.live="type" label="Type">
                @foreach (ProjectMaterialMovement::TYPES as $key => $label) <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option> @endforeach
            </flux:select>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="number" step="0.001" wire:model="quantity" label="Quantity" />
                @if (in_array($type, ['received', 'ordered'], true))
                    <flux:input type="number" step="0.01" wire:model="unit_cost" label="Unit cost" />
                @endif
                <flux:input type="date" wire:model="movement_date" label="Date" />
                @if (in_array($type, ['received', 'ordered', 'returned'], true))
                    <flux:select wire:model="supplier_id" label="Supplier">
                        <flux:select.option value="">—</flux:select.option>
                        @foreach ($suppliers as $s) <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option> @endforeach
                    </flux:select>
                @endif
                <flux:input wire:model="reference" label="PO / delivery note / reference" />
            </div>
            <flux:input wire:model="notes" label="Notes" />
            <flux:input type="file" wire:model="attachment" label="Delivery note / invoice (optional)" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Record</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
