<?php

use App\Exports\Accounting\JournalEntriesExport;
use App\Livewire\Concerns\UsesAccounting;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.vendor'), Title('Journal Entries')] class extends Component {
    use UsesAccounting, WithPagination;

    #[Url(history: true)]
    public string $from = '';
    #[Url(history: true)]
    public string $to = '';
    #[Url(as: 'q', history: true)]
    public string $search = '';
    #[Url(history: true)]
    public string $source = '';

    public const SOURCES = [
        'manual' => 'Manual',
        Invoice::class => 'Invoices',
        Payment::class => 'Payments',
        Expense::class => 'Expenses',
        OpeningBalance::class => 'Opening balances',
        \App\Models\Asset::class => 'Depreciation',
    ];

    public function mount(): void
    {
        $this->bootAccounting();
        $this->from = $this->from ?: now()->startOfYear()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function updating($field): void
    {
        $this->resetPage();
    }

    protected function range(): array
    {
        $from = $this->dateOr($this->from, CarbonImmutable::now()->startOfYear());
        $to = $this->dateOr($this->to, CarbonImmutable::now());

        return [$from, $to];
    }

    public function export()
    {
        [$from, $to] = $this->range();
        Flux::toast(variant: 'success', heading: 'Export ready', text: 'Journal entries downloading.');

        return Excel::download(
            new JournalEntriesExport($this->vendorId(), $from->toDateString(), $to->toDateString(), $this->search, $this->source),
            'journal-entries-' . $from->toDateString() . '-to-' . $to->toDateString() . '.xlsx',
        );
    }

    public function with(): array
    {
        [$from, $to] = $this->range();

        return [
            'entries' => JournalEntry::query()
                ->posted()
                ->with(['lines.account', 'creator'])
                ->forPeriod($from->toDateString(), $to->toDateString())
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('reference', 'like', "%{$this->search}%")
                    ->orWhere('memo', 'like', "%{$this->search}%")
                    ->orWhere('id', ltrim($this->search, '#'))))
                ->when($this->source === 'manual', fn ($q) => $q->whereNull('source_type'))
                ->when($this->source && $this->source !== 'manual', fn ($q) => $q->where('source_type', $this->source))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(25),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="book-open" title="Journal entries"
            subtitle="Every posted entry. Posted entries are never edited — reverse them instead.">
            <x-slot:actions>
                @can('accounting.post')
                    <flux:button href="{{ route('vendor.accounting.journal.create') }}" variant="primary" icon="plus" wire:navigate>
                        Manual entry
                    </flux:button>
                @endcan
            </x-slot:actions>
        </x-accounting.card-header>

        <div class="flex flex-col lg:flex-row gap-3 lg:items-end">
            <x-accounting.date-range />
            <flux:input wire:model.live.debounce.300ms="search" label="Search" icon="magnifying-glass" placeholder="#id, reference, memo…" class="lg:w-64" />
            <flux:select wire:model.live="source" label="Source" class="lg:w-48">
                <flux:select.option value="">All sources</flux:select.option>
                @foreach ($this::SOURCES as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button wire:click="export" icon="arrow-down-tray" variant="filled">Export</flux:button>
        </div>
    </flux:card>

    <flux:table :paginate="$entries">
        <flux:table.columns>
            <flux:table.column>#</flux:table.column>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Memo</flux:table.column>
            <flux:table.column>Accounts</flux:table.column>
            <flux:table.column align="end">Amount</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($entries as $e)
                <flux:table.row :key="$e->id">
                    <flux:table.cell class="font-mono">
                        <flux:link href="{{ route('vendor.accounting.journal.show', $e) }}" wire:navigate>#{{ $e->id }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $e->date->format('d M Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ \Illuminate\Support\Str::limit($e->memo, 70) }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ $e->reference }}
                            {{ $e->source_type ? '· ' . ($this::SOURCES[$e->source_type] ?? class_basename($e->source_type)) : '· Manual' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                        @foreach ($e->lines->take(4) as $l)
                            <div wire:key="l-{{ $l->id }}">{{ $l->account->code }} {{ $l->account->name }}</div>
                        @endforeach
                        @if ($e->lines->count() > 4)
                            <div>+{{ $e->lines->count() - 4 }} more</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$e->total_debit" /></flux:table.cell>
                    <flux:table.cell>
                        @if ($e->reversed_by_id)
                            <flux:badge size="sm" color="zinc">Reversed</flux:badge>
                        @elseif ($e->reverses_id)
                            <flux:badge size="sm" color="amber">Reversal</flux:badge>
                        @else
                            <flux:badge size="sm" color="emerald">Posted</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-10">No entries in this range.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
