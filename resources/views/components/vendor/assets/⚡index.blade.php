<?php

use App\Enums\AssetCategory;
use App\Models\Asset;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Assets')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]  public string $search = '';
    #[Url(history: true)]           public string $category = '';
    #[Url(history: true)]           public string $status = '';

    public function updating(): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $asset = Asset::findOrFail($id);
        $this->authorize('delete', $asset);

        $asset->delete();
        \Flux\Flux::toast(variant: 'success', text: 'Asset deleted.');
    }

    public function with(): array
    {
        $assets = Asset::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('identifier', 'like', "%{$this->search}%")
            ))
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('purchase_date')
            ->paginate(20);

        $totalCost = Asset::where('status', 'active')->sum('purchase_cost');

        // Sum book values in PHP (depreciation is computed, not stored)
        $bookValue = Asset::where('status', 'active')->get()->sum(fn ($a) => $a->bookValue());

        return [
            'assets' => $assets,
            'summary' => [
                'count'      => Asset::where('status', 'active')->count(),
                'total_cost' => $totalCost,
                'book_value' => $bookValue,
                'depreciated'=> $totalCost - $bookValue,
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    <div class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Active assets</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">{{ $summary['count'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Total cost</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 font-mono">{{ number_format($summary['total_cost'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Accumulated depreciation</span>
            <div class="text-2xl font-semibold text-rose-600 mt-1 font-mono">{{ number_format($summary['depreciated'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Net book value</span>
            <div class="text-2xl font-semibold text-accent-600 mt-1 font-mono">{{ number_format($summary['book_value'], 2) }}</div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search name or identifier…" class="w-full sm:w-72" />

            <flux:select wire:model.live="category" class="w-full sm:w-48">
                <flux:select.option value="">All categories</flux:select.option>
                @foreach (AssetCategory::cases() as $c)
                    <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" class="w-full sm:w-40">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="disposed">Disposed</flux:select.option>
                <flux:select.option value="retired">Retired</flux:select.option>
            </flux:select>
        </div>

        <flux:button href="{{ route('vendor.assets.create') }}" variant="primary" icon="plus">
            Add asset
        </flux:button>
    </div>

    <flux:table :paginate="$assets">
        <flux:table.columns>
            <flux:table.column>Asset</flux:table.column>
            <flux:table.column>Category</flux:table.column>
            <flux:table.column>Purchased</flux:table.column>
            <flux:table.column align="end">Cost</flux:table.column>
            <flux:table.column align="end">Book value</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($assets as $asset)
                <flux:table.row :key="$asset->id">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $asset->name }}</div>
                        @if ($asset->identifier)
                            <div class="text-xs text-zinc-500 font-mono">{{ $asset->identifier }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge color="zinc" size="sm">{{ $asset->category->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                        {{ $asset->purchase_date->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format((float) $asset->purchase_cost, 2) }}
                    </flux:table.cell>
                    <flux:table.cell align="end" variant="strong" class="font-mono text-accent-600">
                        {{ number_format($asset->bookValue(), 2) }}
                    </flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge :color="match($asset->status) {
                            'active'   => 'emerald',
                            'disposed' => 'zinc',
                            default    => 'amber',
                        }" size="sm">
                            {{ ucfirst($asset->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.assets.edit', $asset) }}" />
                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $asset->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-12 text-zinc-500">
                        No assets recorded yet.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-asset" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete asset?</flux:heading>
                <flux:subheading>Cannot be undone. If the asset was sold, mark it as disposed instead.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)"
                    x-on:click="confirmDelete = null">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>