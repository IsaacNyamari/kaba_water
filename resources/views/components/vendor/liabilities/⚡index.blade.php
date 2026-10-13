<?php

use App\Enums\LiabilityCategory;
use App\Models\Liability;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Liabilities')] class extends Component
{
    use WithPagination;

    #[Url(history: true)] public string $category = '';
    #[Url(history: true)] public string $status = '';

    public function updating(): void { $this->resetPage(); }

    public function recordPayment(int $id, string $amount): void
    {
        $liability = Liability::findOrFail($id);
        $this->authorize('update', $liability);

        $pay = round((float) $amount, 2);
        if ($pay <= 0) return;

        // Never record more than is owed.
        $pay = min($pay, $liability->outstanding());
        if ($pay <= 0) return;

        $liability->increment('amount_paid', $pay);

        if ($liability->fresh()->outstanding() <= 0) {
            $liability->update(['status' => 'paid']);
        }

        \Flux\Flux::toast(variant: 'success', text: 'Payment recorded against ' . $liability->name . '.');
    }

    public function delete(int $id): void
    {
        $liability = Liability::findOrFail($id);
        $this->authorize('delete', $liability);

        $liability->delete();
        \Flux\Flux::toast(variant: 'success', text: 'Liability deleted.');
    }

    public function with(): array
    {
        $query = Liability::query()
            ->with('supplier')
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->status,   fn ($q) => $q->where('status', $this->status))
            ->orderBy('due_at')
            ->paginate(20);

        $outstandingTotal = Liability::where('status', 'active')
            ->get()
            ->sum(fn ($l) => $l->outstanding());

        return [
            'liabilities' => $query,
            'summary' => [
                'outstanding_total' => $outstandingTotal,
                'active_count'      => Liability::where('status', 'active')->count(),
                'overdue_count'     => Liability::where('status', 'active')
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->count(),
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null, payFor: null, payAmount: '' }">

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Total outstanding</span>
            <div class="text-2xl font-semibold text-rose-600 mt-1 font-mono">{{ number_format($summary['outstanding_total'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Active liabilities</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">{{ $summary['active_count'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Overdue</span>
            <div class="text-2xl font-semibold text-amber-600 mt-1">{{ $summary['overdue_count'] }}</div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:select wire:model.live="category" class="w-full sm:w-52">
                <flux:select.option value="">All categories</flux:select.option>
                @foreach (LiabilityCategory::cases() as $c)
                    <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" class="w-full sm:w-40">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="paid">Paid</flux:select.option>
                <flux:select.option value="defaulted">Defaulted</flux:select.option>
            </flux:select>
        </div>

        <flux:button href="{{ route('vendor.liabilities.create') }}" variant="primary" icon="plus">
            Add liability
        </flux:button>
    </div>

    <flux:table :paginate="$liabilities">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Category</flux:table.column>
            <flux:table.column align="end">Principal</flux:table.column>
            <flux:table.cell align="end">Paid</flux:table.cell>
            <flux:table.column align="end">Outstanding</flux:table.column>
            <flux:table.column>Due</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($liabilities as $l)
                <flux:table.row :key="$l->id">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $l->name }}</div>
                        @if ($l->supplier)
                            <div class="text-xs text-zinc-500">{{ $l->supplier->name }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge color="zinc" size="sm">{{ $l->category->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ number_format((float) $l->principal, 2) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono text-accent-600">{{ number_format((float) $l->amount_paid, 2) }}</flux:table.cell>
                    <flux:table.cell align="end" variant="strong" class="font-mono text-rose-600">
                        {{ number_format($l->outstanding(), 2) }}
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                        {{ $l->due_at?->format('d M Y') ?? '—' }}
                        @if ($l->status === 'active' && $l->due_at?->isPast())
                            <div class="text-xs text-amber-600">overdue</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            @if ($l->status === 'active' && $l->outstanding() > 0)
                                <flux:button size="sm" variant="ghost" icon="banknotes"
                                    @click="payFor = {{ $l->id }}; payAmount = '{{ $l->outstanding() }}'" />
                            @endif
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.liabilities.edit', $l) }}" />
                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $l->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-12 text-zinc-500">
                        No liabilities recorded.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Payment modal --}}
    <flux:modal name="pay-liability" x-model="payFor" class="w-full max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">Record payment</flux:heading>
            <flux:input type="number" step="0.01" min="0.01" x-model="payAmount" label="Amount paid" />
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" @click="payFor = null">Cancel</flux:button>
                <flux:button variant="primary" @click="$wire.recordPayment(payFor, payAmount); payFor = null">
                    Record payment
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete modal --}}
    <flux:modal name="delete-liability" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete liability?</flux:heading>
                <flux:subheading>This cannot be undone.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" @click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" @click="$wire.delete(confirmDelete); confirmDelete = null">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>