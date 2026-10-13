<?php

use App\Enums\AssetCategory;
use App\Models\Asset;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor')] class extends Component {
    public ?Asset $asset = null;

    public string $name = '';
    public string $category = 'equipment';
    public string $identifier = '';
    public string $purchase_date = '';
    public string $purchase_cost = '';
    public string $salvage_value = '0';
    public string $depreciation_rate = '0';
    public string $status = 'active';
    public string $disposal_date = '';
    public string $disposal_value = '';
    public string $notes = '';

    public function mount(?Asset $asset = null): void
    {
        if ($asset && $asset->exists) {
            $this->authorize('update', $asset);
            $this->asset = $asset;
            $this->name = $asset->name;
            $this->category = $asset->category->value;
            $this->identifier = $asset->identifier ?? '';
            $this->purchase_date = $asset->purchase_date->format('Y-m-d');
            $this->purchase_cost = (string) $asset->purchase_cost;
            $this->salvage_value = (string) $asset->salvage_value;
            $this->depreciation_rate = (string) $asset->depreciation_rate;
            $this->status = $asset->status;
            $this->disposal_date = $asset->disposal_date?->format('Y-m-d') ?? '';
            $this->disposal_value = (string) ($asset->disposal_value ?? '');
            $this->notes = $asset->notes ?? '';
        } else {
            $this->authorize('create', Asset::class);
            $this->purchase_date = now()->format('Y-m-d');
        }
    }

    public function title(): string
    {
        return $this->asset ? 'Edit asset' : 'New asset';
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:' . collect(AssetCategory::cases())->pluck('value')->implode(',')],
            'identifier' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['required', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'depreciation_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:active,disposed,retired'],
            'disposal_date' => ['nullable', 'date'],
            'disposal_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name' => $this->name,
            'category' => $this->category,
            'identifier' => $this->identifier ?: null,
            'purchase_date' => $this->purchase_date,
            'purchase_cost' => $this->purchase_cost,
            'salvage_value' => $this->salvage_value ?: 0,
            'depreciation_rate' => $this->depreciation_rate ?: 0,
            'status' => $this->status,
            'disposal_date' => $this->disposal_date ?: null,
            'disposal_value' => $this->disposal_value !== '' ? $this->disposal_value : null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->asset) {
            $this->asset->update($payload);
            session()->flash('status', 'Asset updated.');
        } else {
            Asset::create($payload);
            session()->flash('status', 'Asset created.');
        }

        $this->redirectRoute('vendor.assets.index', navigate: true);
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl space-y-6">
    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-5">
            <flux:heading size="sm">{{ $asset ? 'Edit asset' : 'New asset' }}</flux:heading>

            <flux:input wire:model="name" label="Asset name" />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="category" label="Category">
                    @foreach (AssetCategory::cases() as $c)
                        <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="identifier" label="Identifier (serial, plate no)" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input type="date" wire:model="purchase_date" label="Purchase date" />
                <flux:input wire:model="purchase_cost" type="number" step="0.01" min="0"
                    label="Purchase cost" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="salvage_value" type="number" step="0.01" min="0"
                    label="Salvage value" description="Residual value at end of life." />
                <flux:input wire:model="depreciation_rate" type="number" step="0.01" min="0" max="100"
                    label="Depreciation rate (% per year)"
                    description="Straight-line. Use 0 for non-depreciating assets." />
            </div>
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="sm">Status</flux:heading>

            <flux:select wire:model="status" label="Status">
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="disposed">Disposed</flux:select.option>
                <flux:select.option value="retired">Retired</flux:select.option>
            </flux:select>

            @if (in_array($status, ['disposed', 'retired']))
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input type="date" wire:model="disposal_date" label="Disposal/retirement date" />
                    <flux:input wire:model="disposal_value" type="number" step="0.01" min="0"
                        label="Sale proceeds" description="Leave blank if scrapped." />
                </div>
            @endif

            <flux:textarea wire:model="notes" label="Notes" rows="3" />
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.assets.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $asset ? 'Save changes' : 'Create asset' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
