<?php

use App\Livewire\Concerns\RefreshesOnActivity;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor', ['title' => 'Invoices'])] class extends Component {
    use WithPagination, RefreshesOnActivity;

    #[Url(as: 'q', history: true)]
    public string $search = '';
    #[Url(history: true)]
    public string $status = '';
    #[Url(as: 'client', history: true)]
    public ?int $clientId = null;
    #[Url(as: 'from', history: true)]
    public ?string $from = null;
    #[Url(as: 'to', history: true)]
    public ?string $to = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'clientId', 'from', 'to']);
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'invoices' => Invoice::query()
                ->with(['client', 'payments'])
                ->when($this->search, fn($q) => $q->where(fn($q) => $q->where('number', 'like', "%{$this->search}%")->orWhereHas('client', fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('account_no', 'like', "%{$this->search}%"))))
                ->when($this->status, fn($q) => $q->where('status', $this->status))
                ->when($this->clientId, fn($q) => $q->where('client_id', $this->clientId))
                ->when($this->from, fn($q) => $q->whereDate('issued_at', '>=', $this->from))
                ->when($this->to, fn($q) => $q->whereDate('issued_at', '<=', $this->to))
                ->latest('issued_at')
                ->paginate(20),

            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),

            'summary' => [
                'unpaid_total' => Invoice::whereIn('status', ['sent', 'overdue'])->sum(\DB::raw('total - amount_paid')),
                'paid_total' => Invoice::where('status', 'paid')->sum('total'),
                'count' => Invoice::count(),
            ],
        ];
    }
};
?>

<div class="space-y-4">

    {{-- KPI strip — revenue totals are hidden from meter readers --}}
    @can('vendor.payments.view')
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Total invoices', number_format($summary['count']), 'document-text', 'brand', 'text-brand-800 dark:text-brand-200', 'bg-brand-500'],
            ['Unpaid', number_format($summary['unpaid_total'], 2), 'clock', 'amber', 'text-amber-800 dark:text-amber-200', 'bg-amber-500'],
            ['Paid', number_format($summary['paid_total'], 2), 'check-circle', 'emerald', 'text-emerald-800 dark:text-emerald-200', 'bg-emerald-500'],
        ] as [$label, $value, $icon, $tone, $valueClass, $chipClass])
            <div wire:key="inv-kpi-{{ $loop->index }}" class="stat-card tone-{{ $tone }} p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
                    <span class="flex size-9 items-center justify-center rounded-xl text-white shadow-sm {{ $chipClass }}">
                        <flux:icon :name="$icon" variant="mini" class="size-5" />
                    </span>
                </div>
                <div class="mt-3 font-mono text-2xl font-semibold tracking-tight {{ $valueClass }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>
    @endcan

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search number or client…" class="w-full sm:w-72" />

            <flux:select wire:model.live="status" class="w-full sm:w-40">
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (InvoiceStatus::cases() as $s)
                    <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="clientId" class="w-full sm:w-60">
                <flux:select.option value="">All clients</flux:select.option>
                @foreach ($clients as $c)
                    <flux:select.option value="{{ $c->id }}">
                        {{ $c->account_no }} · {{ $c->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="from" class="w-full sm:w-40" />
            <flux:input type="date" wire:model.live="to" class="w-full sm:w-40" />

            @if ($search || $status || $clientId || $from || $to)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>

        <flux:button href="{{ route('vendor.invoices.create') }}" variant="primary" icon="plus">
            New invoice
        </flux:button>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <flux:table :paginate="$invoices">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Client</flux:table.column>
                <flux:table.column>Issued</flux:table.column>
                <flux:table.column>Due</flux:table.column>
                <flux:table.column align="end">Total</flux:table.column>
                <flux:table.column align="end">Balance</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell class="font-mono text-xs">
                            <a href="{{ route('vendor.invoices.show', $invoice) }}"
                                class="text-brand-600 hover:underline">{{ $invoice->number }}</a>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $invoice->client->name }}
                            </div>
                            <div class="text-xs text-zinc-500 font-mono">
                                {{ $invoice->client->account_no }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                            {{ $invoice->issued_at->format('d M Y') }}
                        </flux:table.cell>

                        <flux:table.cell class="whitespace-nowrap">
                            {{ $invoice->due_at->format('d M Y') }}
                            @if ($invoice->isOverdue())
                                <div class="text-xs text-rose-600">{{ $invoice->due_at->diffForHumans() }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end" class="font-mono">
                            {{ number_format($invoice->total, 2) }}
                        </flux:table.cell>

                        <flux:table.cell align="end" class="font-mono">
                            @if ($invoice->balance() > 0)
                                <span class="text-brand-700 dark:text-brand-400 font-semibold">
                                    {{ number_format($invoice->balance(), 2) }}
                                </span>
                            @else
                                <span class="text-accent-600">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="py-0">
                            <flux:badge :color="$invoice->status->color()" size="sm">
                                {{ $invoice->status->label() }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="inline-flex gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye"
                                    href="{{ route('vendor.invoices.show', $invoice) }}" />
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                                    href="{{ route('vendor.invoices.pdf', $invoice) }}" target="_blank" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500 py-10">
                            No invoices yet.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
