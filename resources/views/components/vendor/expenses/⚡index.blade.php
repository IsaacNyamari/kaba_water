<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Expenses')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]       public string $search = '';
    #[Url(history: true)]                public string $category = '';
    #[Url(history: true)]                public ?int $supplierId = null;
    #[Url(history: true)]                public ?string $from = null;
    #[Url(history: true)]                public ?string $to = null;

    public function updating(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'supplierId', 'from', 'to']);
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $expense = Expense::findOrFail($id);
        $this->authorize('delete', $expense);

        $expense->delete();
        session()->flash('status', 'Expense deleted.');
    }

    public function with(): array
    {
        return [
            'expenses' => Expense::query()
                ->with(['category', 'supplier', 'workOrder'])
                ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                    $q->where('description', 'like', "%{$this->search}%")
                      ->orWhere('reference', 'like', "%{$this->search}%")
                ))
                ->when($this->category, fn ($q) => $q->whereHas(
                    'category', fn ($q) => $q->where('slug', $this->category)
                ))
                ->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId))
                ->when($this->from, fn ($q) => $q->whereDate('paid_at', '>=', $this->from))
                ->when($this->to,   fn ($q) => $q->whereDate('paid_at', '<=', $this->to))
                ->latest('paid_at')
                ->paginate(20),

            'categories' => ExpenseCategory::orderBy('name')->get(),
            'suppliers'  => Supplier::orderBy('name')->get(['id', 'name']),

            'summary' => [
                'this_month' => Expense::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
                'this_year'  => Expense::whereYear('paid_at', now()->year)->sum('amount'),
                'all_time'   => Expense::sum('amount'),
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    {{-- Summary --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">This month</span>
            <div class="text-2xl font-semibold text-rose-600 mt-1">{{ number_format($summary['this_month'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">This year</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">{{ number_format($summary['this_year'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">All time</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">{{ number_format($summary['all_time'], 2) }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search description or reference…" class="w-full sm:w-72" />

            <flux:select wire:model.live="category" class="w-full sm:w-52">
                <flux:select.option value="">All categories</flux:select.option>
                @foreach ($categories as $c)
                    <flux:select.option value="{{ $c->slug }}">{{ $c->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="supplierId" class="w-full sm:w-52">
                <flux:select.option value="">All suppliers</flux:select.option>
                @foreach ($suppliers as $s)
                    <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="from" class="w-full sm:w-40" />
            <flux:input type="date" wire:model.live="to"   class="w-full sm:w-40" />

            @if ($search || $category || $supplierId || $from || $to)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>

        <flux:button href="{{ route('vendor.expenses.create') }}" variant="primary" icon="plus">
            Record expense
        </flux:button>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$expenses">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Category</flux:table.column>
            <flux:table.column>Description</flux:table.column>
            <flux:table.column>Supplier</flux:table.column>
            <flux:table.column>Reference</flux:table.column>
            <flux:table.column align="end">Amount</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($expenses as $expense)
                <flux:table.row :key="$expense->id">
                    <flux:table.cell class="whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                        {{ $expense->paid_at->format('d M Y') }}
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        <flux:badge :color="$expense->category->color" size="sm">
                            {{ $expense->category->name }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="max-w-sm truncate">
                        {{ $expense->description }}
                        @if ($expense->workOrder)
                            <div class="text-xs text-zinc-500 font-mono">
                                WO {{ $expense->workOrder->number }}
                            </div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        {{ $expense->supplier?->name ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs text-zinc-500">
                        {{ $expense->reference ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell align="end" variant="strong" class="font-mono text-rose-600">
                        {{ number_format($expense->amount, 2) }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.expenses.edit', $expense) }}" />
                            <flux:button size="sm" variant="ghost" icon="trash"
                                class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $expense->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-12 text-zinc-500">
                        No expenses recorded yet.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-expense" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete expense?</flux:heading>
                <flux:subheading>This cannot be undone.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)"
                    x-on:click="confirmDelete = null">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>