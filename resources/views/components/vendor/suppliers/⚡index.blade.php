<?php

use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Suppliers')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)] public string $search = '';

    public function updatingSearch(): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('delete', $supplier);

        if ($supplier->expenses()->exists()) {
            session()->flash('error', 'Cannot delete a supplier with linked expenses.');
            return;
        }

        $supplier->delete();
        session()->flash('status', 'Supplier deleted.');
    }

    public function with(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->withCount('expenses')
                ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                      ->orWhere('phone', 'like', "%{$this->search}%")
                ))
                ->orderBy('name')
                ->paginate(20),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Search suppliers…" class="w-full sm:w-72" />
        <flux:button href="{{ route('vendor.suppliers.create') }}" variant="primary" icon="plus">
            New supplier
        </flux:button>
    </div>

    <flux:table :paginate="$suppliers">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Contact</flux:table.column>
            <flux:table.column align="end">Expenses</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($suppliers as $supplier)
                <flux:table.row :key="$supplier->id">
                    <flux:table.cell variant="strong">
                        {{ $supplier->name }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        @if ($supplier->phone) <div>{{ $supplier->phone }}</div> @endif
                        @if ($supplier->email) <div class="text-xs">{{ $supplier->email }}</div> @endif
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format($supplier->expenses_count) }}
                    </flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge :color="$supplier->status === 'active' ? 'emerald' : 'zinc'" size="sm">
                            {{ ucfirst($supplier->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.suppliers.edit', $supplier) }}" />
                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $supplier->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-12 text-zinc-500">
                        No suppliers yet.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-supplier" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete supplier?</flux:heading>
                <flux:subheading>Cannot be undone. Suppliers with linked expenses cannot be deleted.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)"
                    x-on:click="confirmDelete = null">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>