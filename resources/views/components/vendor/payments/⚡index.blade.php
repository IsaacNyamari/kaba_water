<?php

use App\Models\Client;
use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor', ['title' => 'Payments'])] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';
    #[Url(as: 'client', history: true)]
    public ?int $clientId = null;
    #[Url(as: 'method', history: true)]
    public string $method = '';
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
        $this->reset(['search', 'clientId', 'method', 'from', 'to']);
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $payment = Payment::findOrFail($id);
        $this->authorize('delete', $payment);

        $invoice = $payment->invoice;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $invoice) {
                $payment->delete();

                // Recompute the parent invoice's amount_paid + status (paid / sent / overdue)
                $invoice?->syncPayments();
            });
        } catch (\App\Services\Accounting\Exceptions\AccountingException $e) {
            \Flux\Flux::toast(variant: 'danger', heading: 'Cannot delete payment', text: $e->getMessage());
            return;
        }

        if ($invoice) {
            rescue(fn () => app(\App\Services\InvoicePdfService::class)->regenerate($invoice));
        }

        \Flux\Flux::toast(variant: 'success', text: 'Payment deleted.');
    }

    public function with(): array
    {
        return [
            'payments' => Payment::query()
                ->with(['client', 'invoice'])
                ->when($this->search, fn($q) => $q->where(fn($q) => $q->where('reference', 'like', "%{$this->search}%")->orWhereHas('client', fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('account_no', 'like', "%{$this->search}%"))))
                ->when($this->clientId, fn($q) => $q->where('client_id', $this->clientId))
                ->when($this->method, fn($q) => $q->where('method', $this->method))
                ->when($this->from, fn($q) => $q->whereDate('paid_at', '>=', $this->from))
                ->when($this->to, fn($q) => $q->whereDate('paid_at', '<=', $this->to))
                ->latest('paid_at')
                ->paginate(20),

            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),

            'summary' => [
                'today' => Payment::whereDate('paid_at', today())->sum('amount'),
                'this_month' => Payment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
                'total' => Payment::sum('amount'),
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Today</span>
            <div class="text-2xl font-semibold text-accent-600 mt-1">
                {{ number_format($summary['today'], 2) }}
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">This month</span>
            <div class="text-2xl font-semibold text-brand-600 mt-1">
                {{ number_format($summary['this_month'], 2) }}
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">All time</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                {{ number_format($summary['total'], 2) }}
            </div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search reference or client…" class="w-full sm:w-72" />

            <flux:select wire:model.live="clientId" class="w-full sm:w-56">
                <flux:select.option value="">All clients</flux:select.option>
                @foreach ($clients as $c)
                    <flux:select.option value="{{ $c->id }}">
                        {{ $c->account_no }} · {{ $c->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="method" class="w-full sm:w-40">
                <flux:select.option value="">All methods</flux:select.option>
                @foreach (\App\Enums\PaymentMethod::cases() as $m)
                    <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="from" class="w-full sm:w-40" />
            <flux:input type="date" wire:model.live="to" class="w-full sm:w-40" />

            @if ($search || $clientId || $method || $from || $to)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>

        <flux:button href="{{ route('vendor.payments.create') }}" variant="primary" icon="plus">
            Record payment
        </flux:button>
    </div>

    <flux:table :paginate="$payments">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Client</flux:table.column>
            <flux:table.column>Invoice</flux:table.column>
            <flux:table.column>Method</flux:table.column>
            <flux:table.column>Reference</flux:table.column>
            <flux:table.column align="end">Amount</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($payments as $payment)
                <flux:table.row :key="$payment->id">
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $payment->paid_at->format('d M Y') }}
                        <div class="text-xs text-zinc-500">{{ $payment->paid_at->format('H:i') }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $payment->client->name }}
                        </div>
                        <div class="text-xs text-zinc-500 font-mono">
                            {{ $payment->client->account_no }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs">
                        @if ($payment->invoice)
                            <a href="{{ route('vendor.invoices.show', $payment->invoice) }}"
                                class="text-brand-600 hover:underline">
                                {{ $payment->invoice->number }}
                            </a>
                        @else
                            —
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        {{ $payment->method->label() }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs text-zinc-500">
                        {{ $payment->reference ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell align="end" variant="strong" class="font-mono text-accent-600">
                        {{ number_format($payment->amount, 2) }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                            wire:click="delete({{ $payment->id }})" wire:confirm="Delete this payment?" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500 py-10">
                        No payments recorded.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
