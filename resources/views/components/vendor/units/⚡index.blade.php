<?php

use App\Models\Client;
use App\Models\Property;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Houses')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $property = '';

    /** '' | occupied | vacant */
    #[Url(history: true)]
    public string $occupancy = '';

    // ── House form ──
    public ?int $editingId = null;
    public string $house_number = '';
    public ?int $property_id = null;
    public string $type = 'apartment';
    /** existing | new | none */
    public string $tenant_mode = 'none';
    public ?int $client_id = null;
    public string $tenant_name = '';
    public string $tenant_phone = '';
    public string $rent = '0';
    public string $service_charge = '0';
    public string $deposit = '0';
    public string $notes = '';

    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Unit::class);
    }

    public function updating(string $field): void
    {
        if (in_array($field, ['search', 'property', 'occupancy'], true)) {
            $this->resetPage();
        }
    }

    // ───────────────────────── Houses ─────────────────────────

    public function openCreate(): void
    {
        $this->authorize('create', Unit::class);

        $this->resetHouseForm();
        $this->property_id = $this->property !== '' ? (int) $this->property : null;
        Flux::modal('house-form')->show();
    }

    public function openEdit(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $this->authorize('update', $unit);

        $this->resetHouseForm();
        $this->editingId = $unit->id;
        $this->house_number = $unit->house_number;
        $this->property_id = $unit->property_id;
        $this->type = $unit->type;
        $this->tenant_mode = $unit->client_id ? 'existing' : 'none';
        $this->client_id = $unit->client_id;
        $this->rent = (string) $unit->rent;
        $this->service_charge = (string) $unit->service_charge;
        $this->deposit = (string) $unit->deposit;
        $this->notes = (string) $unit->notes;

        Flux::modal('house-form')->show();
    }

    protected function resetHouseForm(): void
    {
        $this->reset(['editingId', 'house_number', 'property_id', 'client_id', 'tenant_name', 'tenant_phone', 'notes']);
        $this->type = 'apartment';
        $this->tenant_mode = 'none';
        $this->rent = $this->service_charge = $this->deposit = '0';
        $this->resetValidation();
    }

    public function save(): void
    {
        $vendorId = auth()->user()->vendor_id;

        $this->validate([
            'house_number' => [
                'required', 'string', 'max:40',
                Rule::unique('units', 'house_number')
                    ->where('vendor_id', $vendorId)
                    ->where(fn ($q) => $this->property_id ? $q->where('property_id', $this->property_id) : $q->whereNull('property_id'))
                    ->ignore($this->editingId),
            ],
            'property_id' => ['nullable', Rule::exists('properties', 'id')->where('vendor_id', $vendorId)],
            'type' => ['required', Rule::in(array_keys(Unit::TYPES))],
            'tenant_mode' => ['required', 'in:existing,new,none'],
            'client_id' => [Rule::requiredIf($this->tenant_mode === 'existing'), 'nullable', Rule::exists('clients', 'id')->where('vendor_id', $vendorId)],
            'tenant_name' => [Rule::requiredIf($this->tenant_mode === 'new'), 'nullable', 'string', 'max:150'],
            'tenant_phone' => ['nullable', 'string', 'max:30'],
            'rent' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'service_charge' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'deposit' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'house_number.unique' => 'That house number is already in use here.',
            'client_id.required' => 'Pick the tenant.',
            'tenant_name.required' => "Enter the new tenant's name.",
        ]);

        $unit = $this->editingId ? Unit::findOrFail($this->editingId) : null;
        $this->authorize($unit ? 'update' : 'create', $unit ?? Unit::class);

        DB::transaction(function () use ($unit, $vendorId) {
            $clientId = match ($this->tenant_mode) {
                'existing' => $this->client_id,
                'new' => Client::create([
                    'account_no' => Client::nextAccountNo($vendorId),
                    'name' => $this->tenant_name,
                    'phone' => $this->tenant_phone ?: null,
                    'house_number' => $this->house_number,
                    'status' => 'active',
                ])->id,
                default => null,
            };

            $payload = [
                'house_number' => trim($this->house_number),
                'property_id' => $this->property_id,
                'type' => $this->type,
                'client_id' => $clientId,
                'rent' => $this->rent,
                'service_charge' => $this->service_charge,
                'deposit' => $this->deposit,
                'notes' => $this->notes ?: null,
            ];

            $unit ? $unit->update($payload) : Unit::create($payload);

            // The house number doubles as the tenant's M-Pesa account reference.
            if ($clientId) {
                Client::whereKey($clientId)->whereNull('house_number')->update(['house_number' => trim($this->house_number)]);
            }
        });

        Flux::modal('house-form')->close();
        Flux::toast(variant: 'success', heading: $unit ? 'House updated' : 'House added', text: "House {$this->house_number} saved.");
        $this->resetHouseForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', Unit::findOrFail($id));
        $this->deleteId = $id;
        Flux::modal('delete-house')->show();
    }

    public function delete(): void
    {
        $unit = Unit::findOrFail($this->deleteId);
        $this->authorize('delete', $unit);

        $unit->delete(); // bills keep their history; their house link is cleared
        $this->deleteId = null;

        Flux::modal('delete-house')->close();
        Flux::toast(variant: 'success', heading: 'House deleted', text: "House {$unit->house_number} removed. Its past bills are kept.");
    }

    // ───────────────────────── Data ─────────────────────────

    #[Computed]
    public function properties()
    {
        return Property::withCount('units')->orderBy('name')->get();
    }

    #[Computed]
    public function clients()
    {
        return Client::where('status', 'active')->orderBy('name')->get(['id', 'name', 'account_no', 'phone']);
    }

    #[Computed]
    public function stats(): array
    {
        $units = Unit::query()->when($this->property !== '', fn ($q) => $q->where('property_id', $this->property));

        return [
            'houses' => (clone $units)->count(),
            'occupied' => (clone $units)->occupied()->count(),
            'rent_roll' => (float) (clone $units)->occupied()->sum(DB::raw('rent + service_charge')),
        ];
    }

    public function with(): array
    {
        $term = trim($this->search);

        return [
            'units' => Unit::query()
                ->with(['property', 'client'])
                ->when($this->property !== '', fn ($q) => $q->where('property_id', $this->property))
                ->when($this->occupancy === 'occupied', fn ($q) => $q->occupied())
                ->when($this->occupancy === 'vacant', fn ($q) => $q->whereNull('client_id'))
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('house_number', 'like', "%{$term}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
                ->orderBy('property_id')
                ->orderBy('house_number')
                ->paginate(25),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">

    {{-- Header --}}
    <div class="card">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <flux:icon.home-modern class="size-4" />
                </div>
                <div>
                    <flux:heading size="sm">Houses</flux:heading>
                    <flux:subheading>Every bill goes to a house number and the tenant living there.</flux:subheading>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button icon="building-office-2" href="{{ route('vendor.apartments.index') }}" wire:navigate>Apartments</flux:button>
                @can('vendor.invoices.manage')
                    <flux:button icon="document-plus" href="{{ route('vendor.units.billing') }}" wire:navigate>Bill houses</flux:button>
                @endcan
                @can('create', \App\Models\Unit::class)
                    <flux:button variant="primary" icon="plus" wire:click="openCreate">Add house</flux:button>
                @endcan
            </div>
        </div>

        <div class="card-body grid gap-4 sm:grid-cols-3">
            <div>
                <div class="text-xs uppercase tracking-wide text-zinc-500">Houses</div>
                <div class="mt-1 font-mono text-xl font-semibold">{{ number_format($this->stats['houses']) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-zinc-500">Occupied / vacant</div>
                <div class="mt-1 font-mono text-xl font-semibold">
                    {{ number_format($this->stats['occupied']) }}
                    <span class="text-zinc-400">/ {{ number_format($this->stats['houses'] - $this->stats['occupied']) }}</span>
                </div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-zinc-500">Monthly rent roll</div>
                <div class="mt-1 font-mono text-xl font-semibold">{{ number_format($this->stats['rent_roll'], 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Search house number, tenant or phone…" class="sm:w-80" />
        <flux:select wire:model.live="property" class="sm:w-56">
            <flux:select.option value="">All apartments</flux:select.option>
            @foreach ($this->properties as $p)
                <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="occupancy" class="sm:w-44">
            <flux:select.option value="">Occupied and vacant</flux:select.option>
            <flux:select.option value="occupied">Occupied</flux:select.option>
            <flux:select.option value="vacant">Vacant</flux:select.option>
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$units">
        <flux:table.columns>
            <flux:table.column>House</flux:table.column>
            <flux:table.column>Tenant</flux:table.column>
            <flux:table.column align="end">Rent</flux:table.column>
            <flux:table.column align="end">Service charge</flux:table.column>
            <flux:table.column align="end">Monthly</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($units as $unit)
                <flux:table.row :key="$unit->id">
                    <flux:table.cell>
                        <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $unit->house_number }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ \App\Models\Unit::TYPES[$unit->type] ?? ucfirst($unit->type) }}@if ($unit->property) · {{ $unit->property->name }}@endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($unit->client)
                            <div class="font-medium">{{ $unit->client->name }}</div>
                            <div class="text-xs text-zinc-500">
                                <span class="font-mono">{{ $unit->client->account_no }}</span>
                                @if ($unit->client->phone) · {{ $unit->client->phone }} @endif
                            </div>
                        @else
                            <flux:badge color="zinc" size="sm">Vacant</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono">{{ number_format((float) $unit->rent, 2) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ number_format((float) $unit->service_charge, 2) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono font-semibold">{{ number_format((float) $unit->monthlyCharge(), 2) }}</flux:table.cell>

                    <flux:table.cell align="end">
                        @can('update', $unit)
                            <div class="inline-flex items-center gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEdit({{ $unit->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" class="text-rose-600" wire:click="confirmDelete({{ $unit->id }})" />
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">
                        No houses yet. Add each house by its house number and link the tenant who lives there.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- House form --}}
    <flux:modal name="house-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit house' : 'Add house' }}</flux:heading>
                <flux:subheading>Bills are addressed to this house number and its tenant.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="house_number" label="House number" placeholder="e.g. A4" />
                <flux:select wire:model="type" label="Type">
                    @foreach (\App\Models\Unit::TYPES as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="sm:col-span-2">
                    <flux:select wire:model="property_id" label="Apartment (optional)" description="The building or estate this house is in. Add apartments on the Apartments page.">
                        <flux:select.option value="">Not in an apartment</flux:select.option>
                        @foreach ($this->properties as $p)
                            <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <flux:separator text="Tenant" />

            <flux:radio.group wire:model.live="tenant_mode" class="flex flex-wrap gap-x-6 gap-y-2">
                <flux:radio value="existing" label="Existing tenant" />
                <flux:radio value="new" label="New tenant" />
                <flux:radio value="none" label="Vacant" />
            </flux:radio.group>

            @if ($tenant_mode === 'existing')
                <flux:select wire:model="client_id" label="Tenant">
                    <flux:select.option value="">Pick the tenant…</flux:select.option>
                    @foreach ($this->clients as $c)
                        <flux:select.option value="{{ $c->id }}">{{ $c->name }} · {{ $c->account_no }}{{ $c->phone ? ' · '.$c->phone : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($tenant_mode === 'new')
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="tenant_name" label="Tenant name" />
                    <flux:input wire:model="tenant_phone" label="Phone" description="Bills are sent here by SMS." />
                </div>
            @endif

            <flux:separator text="Monthly charges" />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="rent" type="number" step="0.01" min="0" label="Rent" />
                <flux:input wire:model="service_charge" type="number" step="0.01" min="0" label="Service charge" description="Garbage, security…" />
                <flux:input wire:model="deposit" type="number" step="0.01" min="0" label="Deposit held" />
            </div>

            <flux:textarea wire:model="notes" label="Notes" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save house' : 'Add house' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete house --}}
    <flux:modal name="delete-house" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete house?</flux:heading>
                <flux:subheading>The house is removed from your list. Its past bills and payments are kept.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
