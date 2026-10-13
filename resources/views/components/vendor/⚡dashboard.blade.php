<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\Reading;
use App\Services\VendorAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Dashboard')] class extends Component {
    #[Computed]
    public function analytics(): VendorAnalytics
    {
        return new VendorAnalytics(auth()->user()->vendor_id);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'clients' => Client::count(),
            'meters' => Meter::count(),
            'readings_30d' => Reading::where('read_at', '>=', now()->subDays(30))->count(),
            'revenue_30d' => Payment::where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            'unpaid' => Invoice::whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])->sum(\DB::raw('total - amount_paid')),
            'overdue' => Invoice::where('status', InvoiceStatus::Overdue)->count(),
        ];
    }

    #[Computed]
    public function revenueChart(): array
    {
        $d = $this->analytics->revenueTrend(12);

        return [
            'type' => 'line',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'label' => 'Paid',
                    'data' => $d['revenue'],
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5,150,105,0.08)',
                    'fill' => true,
                ],
                [
                    'label' => 'Invoiced',
                    'data' => $d['billed'],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37,99,235,0.06)',
                    'borderDash' => [4, 4],
                    'fill' => false,
                ],
            ],
        ];
    }

    #[Computed]
    public function consumptionChart(): array
    {
        $d = $this->analytics->consumptionTrend(12);

        return [
            'type' => 'bar',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'label' => 'm³',
                    'data' => $d['values'],
                    'backgroundColor' => 'rgba(37,99,235,0.7)',
                    'borderColor' => '#2563eb',
                    'borderWidth' => 0,
                    'borderRadius' => 6,
                ],
            ],
        ];
    }

    #[Computed]
    public function statusChart(): array
    {
        $d = $this->analytics->invoiceStatusMix();

        return [
            'type' => 'doughnut',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'data' => $d['values'],
                    'backgroundColor' => ['#a1a1aa', '#2563eb', '#059669', '#e11d48', '#d4d4d8', '#f97316'],
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    #[Computed]
    public function recentInvoices()
    {
        return Invoice::with('client')->latest('issued_at')->take(5)->get();
    }

    #[Computed]
    public function recentPayments()
    {
        return Payment::with('client')->latest('paid_at')->take(5)->get();
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">

    {{-- KPI strip --}}
    <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @php
            $kpis = [
                ['Clients', number_format($this->stats['clients']), 'text-zinc-900 dark:text-zinc-100', route('vendor.clients.index')],
                ['Meters', number_format($this->stats['meters']), 'text-zinc-900 dark:text-zinc-100', route('vendor.meters.index')],
                ['Readings (30d)', number_format($this->stats['readings_30d']), 'text-zinc-900 dark:text-zinc-100', route('vendor.readings.index')],
                ['Payments (30d)', number_format($this->stats['revenue_30d'], 2), 'text-accent-600', route('vendor.payments.index')],
                ['Unpaid balance', number_format($this->stats['unpaid'], 2), 'text-brand-600', route('vendor.invoices.index', ['status' => 'sent'])],
                ['Overdue invoices', number_format($this->stats['overdue']), 'text-rose-600', route('vendor.invoices.index', ['status' => 'overdue'])],
            ];
        @endphp
        @foreach ($kpis as [$label, $value, $color, $href])
            <flux:card wire:key="kpi-{{ $loop->index }}" href="{{ $href }}"
                class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                <span class="text-xs uppercase tracking-wide text-zinc-500">{{ $label }}</span>
                <div class="text-2xl font-semibold mt-1 {{ $color }}">{{ $value }}</div>
            </flux:card>
        @endforeach
    </div>

    {{-- Revenue trend --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Revenue & Invoiced — last 12 months</h2>
                <p class="text-xs text-zinc-500">Solid = payments received · Dashed = invoices issued</p>
            </div>
            <a href="{{ route('vendor.reports.index') }}" class="text-sm text-brand-600 hover:underline">Detailed report
                →</a>
        </div>
        <x-chart :data="$this->revenueChart" height="20rem" :legend="true" />
    </div>

    {{-- Consumption + status mix --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Consumption — last 12 months</h2>
            <x-chart :data="$this->consumptionChart" height="16rem" />
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Invoice status mix</h2>
            <x-chart :data="$this->statusChart" height="16rem" :legend="true" />
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Recent Invoices</h2>
                <a href="{{ route('vendor.invoices.index') }}" class="text-sm text-brand-600 hover:underline">View
                    all</a>
            </div>
            <flux:table class="p-2">
                <flux:table.columns>
                    <flux:table.column>Number</flux:table.column>
                    <flux:table.column>Client</flux:table.column>
                    <flux:table.column align="end">Total</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->recentInvoices as $inv)
                        <flux:table.row :key="$inv->id">
                            <flux:table.cell class="font-mono text-xs">{{ $inv->number }}</flux:table.cell>
                            <flux:table.cell>{{ $inv->client->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-mono">{{ number_format($inv->total, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="py-0">
                                <flux:badge :color="$inv->status->color()" size="sm">{{ $inv->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-6 text-zinc-500">No invoices yet.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Recent Payments</h2>
                <a href="{{ route('vendor.payments.index') }}" class="text-sm text-brand-600 hover:underline">View
                    all</a>
            </div>
            <flux:table class="p-2">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Client</flux:table.column>
                    <flux:table.column>Method</flux:table.column>
                    <flux:table.column align="end">Amount</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->recentPayments as $p)
                        <flux:table.row :key="$p->id">
                            <flux:table.cell>{{ $p->paid_at->format('d M') }}</flux:table.cell>
                            <flux:table.cell>{{ $p->client->name }}</flux:table.cell>
                            <flux:table.cell>{{ $p->method->label() }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-mono text-accent-600">
                                {{ number_format($p->amount, 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-6 text-zinc-500">No payments yet.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</div>
