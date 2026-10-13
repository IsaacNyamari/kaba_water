<?php

use App\Enums\VendorStatus;
use App\Models\Vendor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin'), Title('Vendors')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]      public string $search = '';
    #[Url(history: true)]               public string $status = '';
    #[Url(as: 'plan', history: true)]   public ?int $planId = null;

    public function updating(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'planId']);
        $this->resetPage();
    }

    public function suspend(int $id): void
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorize('suspend', $vendor);

        $vendor->update(['status' => VendorStatus::Suspended]);
        \Flux\Flux::toast(variant: 'success', text: "{$vendor->name} suspended.");
    }

    public function activate(int $id): void
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorize('suspend', $vendor);

        $vendor->update(['status' => VendorStatus::Active]);
        \Flux\Flux::toast(variant: 'success', text: "{$vendor->name} activated.");
    }

    public function delete(int $id): void
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorize('delete', $vendor);

        $name = $vendor->name;
        $vendor->delete();
        \Flux\Flux::toast(variant: 'success', text: "{$name} deleted.");
    }

    public function with(): array
    {
        return [
            'vendors' => Vendor::query()
                ->with('plan')
                ->withCount(['clients', 'meters', 'invoices'])
                ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                      ->orWhere('slug', 'like', "%{$this->search}%")
                ))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->planId, fn ($q) => $q->where('plan_id', $this->planId))
                ->latest()
                ->paginate(20),

            'plans' => \App\Models\Plan::orderBy('name')->get(['id', 'name']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search vendors…" class="w-full sm:w-72" />

            <flux:select wire:model.live="status" class="w-full sm:w-44">
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (VendorStatus::cases() as $s)
                    <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="planId" class="w-full sm:w-48">
                <flux:select.option value="">All plans</flux:select.option>
                @foreach ($plans as $p)
                    <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($search || $status || $planId)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>

        <flux:button href="{{ route('admin.vendors.create') }}" variant="primary" icon="plus">
            New vendor
        </flux:button>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$vendors">
        <flux:table.columns>
            <flux:table.column>Vendor</flux:table.column>
            <flux:table.column>Plan</flux:table.column>
            <flux:table.column align="end">Clients</flux:table.column>
            <flux:table.column align="end">Meters</flux:table.column>
            <flux:table.column align="end">Invoices</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($vendors as $vendor)
                <flux:table.row :key="$vendor->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.vendors.edit', $vendor) }}"
                           class="font-medium text-brand-600 hover:underline">
                            {{ $vendor->name }}
                        </a>
                        <div class="text-xs text-zinc-500">{{ $vendor->email }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $vendor->plan?->name ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format($vendor->clients_count) }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format($vendor->meters_count) }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format($vendor->invoices_count) }}
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        <flux:badge :color="$vendor->status->color()" size="sm">
                            {{ $vendor->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            @if ($vendor->status === \App\Enums\VendorStatus::Suspended)
                                <flux:button size="sm" variant="ghost" icon="play"
                                    wire:click="activate({{ $vendor->id }})"
                                    wire:confirm="Activate {{ $vendor->name }}?" />
                            @else
                                <flux:button size="sm" variant="ghost" icon="pause"
                                    wire:click="suspend({{ $vendor->id }})"
                                    wire:confirm="Suspend {{ $vendor->name }}? They will lose access to the workspace." />
                            @endif

                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('admin.vendors.edit', $vendor) }}" />

                            <flux:button size="sm" variant="ghost" icon="trash"
                                class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $vendor->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-12 text-zinc-500">
                        No vendors found.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Delete confirmation --}}
    <flux:modal name="delete-vendor" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete vendor?</flux:heading>
                <flux:subheading>
                    This permanently removes the vendor and all their clients, meters, readings,
                    invoices, and payments.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)"
                    x-on:click="confirmDelete = null">Delete vendor</flux:button>
            </div>
        </div>
    </flux:modal>
</div>