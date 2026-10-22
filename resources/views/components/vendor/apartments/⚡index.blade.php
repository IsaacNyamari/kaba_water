<?php

use App\Models\Property;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * A landlord's apartment buildings (properties). Each one lists its houses,
 * occupancy and monthly rent roll; houses can be added one by one on the
 * Houses page or many at a time here (e.g. A1 to A20).
 */
new #[Layout('layouts.vendor'), Title('Apartments')] class extends Component {
    /** Most houses one "Add houses" can create. */
    public const MAX_BATCH = 300;

    // ── Apartment form ──
    public ?int $editingId = null;
    public string $name = '';
    public string $location = '';

    // ── Add-houses form ──
    public ?int $batchPropertyId = null;
    public string $prefix = '';
    public string $from = '1';
    public string $to = '10';
    public string $type = 'apartment';
    public string $rent = '0';
    public string $service_charge = '0';
    public string $deposit = '0';

    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Property::class);
    }

    // ───────────────────────── Apartments ─────────────────────────

    public function openCreate(): void
    {
        $this->authorize('create', Property::class);

        $this->reset(['editingId', 'name', 'location']);
        $this->resetValidation();
        Flux::modal('apartment-form')->show();
    }

    public function openEdit(int $id): void
    {
        $property = Property::findOrFail($id);
        $this->authorize('update', $property);

        $this->editingId = $property->id;
        $this->name = $property->name;
        $this->location = (string) $property->location;
        $this->resetValidation();
        Flux::modal('apartment-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('properties', 'name')->where('vendor_id', auth()->user()->vendor_id)->ignore($this->editingId)],
            'location' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'You already have an apartment with that name.']);

        $property = $this->editingId ? Property::findOrFail($this->editingId) : null;
        $this->authorize($property ? 'update' : 'create', $property ?? Property::class);

        $payload = ['name' => trim($this->name), 'location' => $this->location ?: null];
        $property ? $property->update($payload) : $property = Property::create($payload);

        Flux::modal('apartment-form')->close();
        Flux::toast(variant: 'success', heading: $this->editingId ? 'Apartment updated' : 'Apartment added', text: $property->name);

        // A new apartment usually needs its houses next.
        if (! $this->editingId) {
            $this->openAddHouses($property->id);
        }

        $this->reset(['editingId', 'name', 'location']);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', Property::findOrFail($id));
        $this->deleteId = $id;
        Flux::modal('delete-apartment')->show();
    }

    public function delete(): void
    {
        $property = Property::withCount('units')->findOrFail($this->deleteId);
        $this->authorize('delete', $property);
        Flux::modal('delete-apartment')->close();
        $this->deleteId = null;

        if ($property->units_count > 0) {
            Flux::toast(variant: 'danger', heading: 'Apartment not deleted', text: "It still has {$property->units_count} house(s). Delete or move them first.");

            return;
        }

        $property->delete();
        Flux::toast(variant: 'success', heading: 'Apartment deleted', text: $property->name);
    }

    // ───────────────────────── Add many houses ─────────────────────────

    public function openAddHouses(int $propertyId): void
    {
        $property = Property::findOrFail($propertyId);
        $this->authorize('create', Unit::class);

        $this->batchPropertyId = $property->id;
        $this->reset(['prefix']);
        $this->from = '1';
        $this->to = '10';
        $this->type = 'apartment';
        $this->rent = $this->service_charge = $this->deposit = '0';
        $this->resetValidation();
        Flux::modal('add-houses')->show();
    }

    /**
     * House numbers the form would create: prefix + every number from..to,
     * e.g. "A" 1..3 → A1, A2, A3.
     *
     * @return list<string>
     */
    #[Computed]
    public function houseNumbers(): array
    {
        $from = (int) $this->from;
        $to = (int) $this->to;

        if (! ctype_digit($this->from) || ! ctype_digit($this->to) || $to < $from || $to - $from >= self::MAX_BATCH) {
            return [];
        }

        return array_map(fn (int $n) => trim($this->prefix).$n, range($from, $to));
    }

    /** House numbers from the form that the apartment already has. */
    #[Computed]
    public function existingNumbers(): array
    {
        if (! $this->batchPropertyId || $this->houseNumbers === []) {
            return [];
        }

        return Unit::where('property_id', $this->batchPropertyId)
            ->whereIn('house_number', $this->houseNumbers)
            ->pluck('house_number')
            ->all();
    }

    public function addHouses(): void
    {
        $this->authorize('create', Unit::class);
        $vendorId = auth()->user()->vendor_id;

        $this->validate([
            'batchPropertyId' => ['required', Rule::exists('properties', 'id')->where('vendor_id', $vendorId)],
            'prefix' => ['nullable', 'string', 'max:20'],
            'from' => ['required', 'integer', 'min:0', 'max:99999'],
            'to' => ['required', 'integer', 'gte:from', 'max:99999', function ($attribute, $value, $fail) {
                if ((int) $value - (int) $this->from >= self::MAX_BATCH) {
                    $fail('Add at most '.self::MAX_BATCH.' houses at a time.');
                }
            }],
            'type' => ['required', Rule::in(array_keys(Unit::TYPES))],
            'rent' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'service_charge' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'deposit' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ], ['to.gte' => 'The last number must not be smaller than the first.']);

        $new = array_values(array_diff($this->houseNumbers, $this->existingNumbers));
        $skipped = count($this->existingNumbers);

        DB::transaction(function () use ($new) {
            foreach ($new as $houseNumber) {
                Unit::create([
                    'property_id' => $this->batchPropertyId,
                    'house_number' => $houseNumber,
                    'type' => $this->type,
                    'rent' => $this->rent,
                    'service_charge' => $this->service_charge,
                    'deposit' => $this->deposit,
                ]);
            }
        });

        $property = Property::find($this->batchPropertyId);
        Flux::modal('add-houses')->close();
        Flux::toast(
            variant: $new ? 'success' : 'warning',
            heading: count($new).' house(s) added to '.$property?->name,
            text: $skipped ? "{$skipped} already existed and were skipped." : 'Link each tenant on the Houses page.',
        );

        $this->batchPropertyId = null;
        unset($this->apartments);
    }

    // ───────────────────────── Data ─────────────────────────

    #[Computed]
    public function apartments()
    {
        return Property::query()
            ->withCount([
                'units',
                'units as occupied_count' => fn ($q) => $q->whereNotNull('client_id'),
            ])
            ->withSum(['units as rent_roll' => fn ($q) => $q->whereNotNull('client_id')], DB::raw('rent + service_charge'))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function batchProperty(): ?Property
    {
        return $this->batchPropertyId ? Property::find($this->batchPropertyId) : null;
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">

    <div class="card">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <flux:icon.building-office-2 class="size-4" />
                </div>
                <div>
                    <flux:heading size="sm">Apartments</flux:heading>
                    <flux:subheading>Each apartment building and its houses. Add as many as you own.</flux:subheading>
                </div>
            </div>

            @can('create', \App\Models\Property::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">Add apartment</flux:button>
            @endcan
        </div>
    </div>

    @if ($this->apartments->isEmpty())
        <div class="card">
            <div class="card-body py-16 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.building-office-2 class="size-7 text-zinc-400" />
                </div>
                <div class="mt-4 font-medium text-zinc-700 dark:text-zinc-200">No apartments yet</div>
                <div class="mt-1 text-sm text-zinc-500">Add your first apartment building, then add its houses in one go.</div>
            </div>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->apartments as $apartment)
                @php $vacant = $apartment->units_count - $apartment->occupied_count; @endphp
                <div class="card flex flex-col" wire:key="apartment-{{ $apartment->id }}">
                    <div class="card-header flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:heading size="sm" class="truncate">{{ $apartment->name }}</flux:heading>
                            @if ($apartment->location)
                                <flux:subheading class="truncate">{{ $apartment->location }}</flux:subheading>
                            @endif
                        </div>
                        @can('update', $apartment)
                            <div class="flex shrink-0 gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEdit({{ $apartment->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" class="text-rose-600" wire:click="confirmDelete({{ $apartment->id }})" />
                            </div>
                        @endcan
                    </div>

                    <div class="card-body grid flex-1 grid-cols-3 gap-3 text-sm">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Houses</div>
                            <div class="mt-1 font-mono text-lg font-semibold">{{ number_format($apartment->units_count) }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Vacant</div>
                            <div class="mt-1 font-mono text-lg font-semibold {{ $vacant > 0 ? 'text-amber-600' : '' }}">{{ number_format($vacant) }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Rent roll</div>
                            <div class="mt-1 font-mono text-lg font-semibold">{{ number_format((float) $apartment->rent_roll, 2) }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 border-t border-zinc-100 px-5 py-3 dark:border-zinc-700">
                        <flux:button size="sm" icon="home-modern" href="{{ route('vendor.units.index', ['property' => $apartment->id]) }}" wire:navigate>
                            View houses
                        </flux:button>
                        @can('create', \App\Models\Unit::class)
                            <flux:button size="sm" variant="primary" icon="plus" wire:click="openAddHouses({{ $apartment->id }})">Add houses</flux:button>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Apartment form --}}
    <flux:modal name="apartment-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit apartment' : 'Add apartment' }}</flux:heading>
                <flux:subheading>An apartment building, estate or plot you rent out.</flux:subheading>
            </div>

            <flux:input wire:model="name" label="Name" placeholder="e.g. Sunrise Apartments" />
            <flux:input wire:model="location" label="Location (optional)" placeholder="e.g. Kahawa West" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save apartment' : 'Add apartment' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Add many houses --}}
    <flux:modal name="add-houses" class="w-full max-w-2xl">
        <form wire:submit="addHouses" class="space-y-6">
            <div>
                <flux:heading size="lg">Add houses{{ $this->batchProperty ? ' to ' . $this->batchProperty->name : '' }}</flux:heading>
                <flux:subheading>Creates a vacant house for every number in the range. Link tenants afterwards on the Houses page.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model.live.debounce.300ms="prefix" label="Prefix (optional)" placeholder="e.g. A or Block B-" />
                <flux:input wire:model.live.debounce.300ms="from" type="number" min="0" label="From number" />
                <flux:input wire:model.live.debounce.300ms="to" type="number" min="0" label="To number" />
            </div>
            <flux:error name="batchPropertyId" />

            @if ($this->houseNumbers)
                <div class="rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">
                    <strong>{{ count($this->houseNumbers) - count($this->existingNumbers) }}</strong> new house(s):
                    <span class="font-mono">{{ implode(', ', array_slice($this->houseNumbers, 0, 8)) }}{{ count($this->houseNumbers) > 8 ? ' … ' . last($this->houseNumbers) : '' }}</span>
                    @if ($this->existingNumbers)
                        <div class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                            Already there, will be skipped: <span class="font-mono">{{ implode(', ', array_slice($this->existingNumbers, 0, 10)) }}{{ count($this->existingNumbers) > 10 ? '…' : '' }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <flux:separator text="Same for every house (you can change each one later)" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="type" label="Type">
                    @foreach (\App\Models\Unit::TYPES as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="rent" type="number" step="0.01" min="0" label="Monthly rent" />
                <flux:input wire:model="service_charge" type="number" step="0.01" min="0" label="Service charge" />
                <flux:input wire:model="deposit" type="number" step="0.01" min="0" label="Deposit" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">Add houses</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete apartment --}}
    <flux:modal name="delete-apartment" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete apartment?</flux:heading>
                <flux:subheading>Only an apartment with no houses can be deleted.</flux:subheading>
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
