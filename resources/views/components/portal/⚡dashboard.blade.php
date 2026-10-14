<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reading;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

use App\Services\ClientAnalytics;

new #[Layout('layouts.portal'), Title('Dashboard')] class extends Component {
use InteractsWithClient;
    #[Computed]
    public function analytics(): ClientAnalytics
    {
        return new ClientAnalytics($this->client->id);
    }

    #[Computed]
    public function consumptionChart(): array
    {
        $d = $this->analytics->consumptionTrend(12);

        return [
            'type' => 'line',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'label' => 'm³',
                    'data' => $d['values'],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37,99,235,0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }
    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    #[Computed]
    public function balance(): float
    {
        return (float) Invoice::where('client_id', $this->client->id)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->sum(\DB::raw('total - amount_paid'));
    }

    #[Computed]
    public function unpaidInvoices()
    {
        return Invoice::where('client_id', $this->client->id)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->orderBy('due_at')
            ->get();
    }

    #[Computed]
    public function nextDue()
    {
        return $this->unpaidInvoices->first();
    }

    #[Computed]
    public function latestReading()
    {
        return Reading::whereHas('meter', fn($q) => $q->where('client_id', $this->client->id))->latest('read_at')->first();
    }

    #[Computed]
    public function recentInvoices()
    {
        return Invoice::where('client_id', $this->client->id)->where('status', '!=', InvoiceStatus::Draft->value)->latest('issued_at')->limit(5)->get();
    }

    #[Computed]
    public function recentPayments()
    {
        return Payment::where('client_id', $this->client->id)->latest('paid_at')->limit(5)->get();
    }

    #[Computed]
    public function meterCount(): int
    {
        return $this->client->meters()->count();
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">

    {{-- Welcome --}}
    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
            Hello, {{ auth()->user()->name }}
        </h1>
        <p class="text-sm text-zinc-500 mt-1">
            Account {{ $this->client->account_no }} · {{ $this->client->vendor->name }}
        </p>
    </div>

    {{-- KPI cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        <div class="stat-card tone-rose p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Balance due</div>
            <div class="text-2xl font-semibold mt-1 {{ $this->balance > 0 ? 'text-rose-600' : 'text-accent-600' }}">
                {{ number_format($this->balance, 2) }}
                <span class="text-sm text-zinc-400 font-normal">{{ $this->client->vendor->currency }}</span>
            </div>
            @if ($this->nextDue)
                <div class="text-xs text-zinc-500 mt-1">
                    Next due {{ $this->nextDue->due_at->format('d M Y') }}
                </div>
            @else
                <div class="text-xs text-accent-600 mt-1">All settled</div>
            @endif
        </div>

        <div class="stat-card tone-sky p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Last reading</div>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                @if ($this->latestReading)
                    {{ number_format($this->latestReading->reading) }}
                    <span class="text-sm text-zinc-400 font-normal">m³</span>
                @else
                    —
                @endif
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ $this->latestReading?->read_at?->format('d M Y') ?? 'No readings yet' }}
            </div>
        </div>

        <div class="stat-card tone-brand p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Last consumption</div>
            <div class="text-2xl font-semibold text-brand-600 mt-1">
                @if ($this->latestReading?->consumption)
                    +{{ number_format($this->latestReading->consumption) }}
                    <span class="text-sm text-zinc-400 font-normal">m³</span>
                @else
                    —
                @endif
            </div>
            <div class="text-xs text-zinc-500 mt-1">Since previous reading</div>
        </div>

        <div class="stat-card tone-brand p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Meters</div>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                {{ $this->meterCount }}
            </div>
            <a href="{{ route('portal.meter') }}" class="text-xs text-brand-600 hover:underline mt-1 inline-block">
                View details →
            </a>
        </div>
    </div>

    {{-- Two columns: invoices + payments --}}
    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Recent invoices --}}
        <div class="app-card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Recent Invoices</h2>
                <a href="{{ route('portal.invoices.index') }}"
                    class="text-sm text-brand-600 dark:text-brand-400 hover:underline">View all</a>
            </div>
            <flux:table class="p-2">
                <flux:table.columns>
                    <flux:table.column>Number</flux:table.column>
                    <flux:table.column>Issued</flux:table.column>
                    <flux:table.column align="end">Total</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->recentInvoices as $inv)
                        <flux:table.row :key="$inv->id">
                            <flux:table.cell class="font-mono text-xs">
                                <a href="{{ route('portal.invoices.show', $inv) }}"
                                    class="text-brand-600 hover:underline">{{ $inv->number }}</a>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                {{ $inv->issued_at->format('d M') }}
                            </flux:table.cell>
                            <flux:table.cell align="end" class="font-mono">
                                {{ number_format($inv->total, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="py-0">
                                <flux:badge :color="$inv->status->color()" size="sm">
                                    {{ $inv->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                                No invoices yet.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="sm">My consumption — last 12 months</flux:heading>
                    <flux:text class="text-xs text-zinc-500">Total m³ consumed per month</flux:text>
                </div>
            </div>
            <x-chart :data="$this->consumptionChart" height="16rem" />
        </flux:card>
        {{-- Recent payments --}}
        <div class="app-card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Recent Payments</h2>
                <a href="{{ route('portal.payments.index') }}"
                    class="text-sm text-brand-600 dark:text-brand-400 hover:underline">View all</a>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="text-left text-xs uppercase tracking-wide text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->recentPayments as $p)
                        <tr wire:key="pay-{{ $p->id }}"
                            class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                            <td class="px-5 py-3 whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                {{ $p->paid_at->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3">{{ $p->method->label() }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-zinc-500">
                                {{ $p->reference ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-medium text-accent-600">
                                {{ number_format($p->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-zinc-500">No payments yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
