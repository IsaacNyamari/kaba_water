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
            'active_meters' => Meter::where('status', 'active')->count(),
            'readings_30d' => Reading::where('read_at', '>=', now()->subDays(30))->count(),
            'revenue_30d' => Payment::where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            'payments_30d' => Payment::where('paid_at', '>=', now()->subDays(30))->count(),
            'unpaid' => Invoice::whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])->sum(\DB::raw('total - amount_paid')),
            'unpaid_count' => Invoice::whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])->count(),
            'overdue' => Invoice::where('status', InvoiceStatus::Overdue)->count(),
            'overdue_amount' => Invoice::where('status', InvoiceStatus::Overdue)->sum(\DB::raw('total - amount_paid')),
        ];
    }

    /** Things that need doing, shown only when there is something to do. */
    #[Computed]
    public function attention(): array
    {
        $pending = Reading::pending()->count();
        $unbilled = Reading::approved()->whereDoesntHave('invoice')->where('consumption', '>', 0)->count();
        $drafts = Invoice::where('status', InvoiceStatus::Draft)->count();

        return array_values(array_filter([
            $pending ? ['clock', "{$pending} client ".str('reading')->plural($pending).' to review', route('vendor.readings.index', ['status' => 'pending'])] : null,
            $unbilled ? ['document-plus', "{$unbilled} ".str('reading')->plural($unbilled).' ready to bill', route('vendor.invoices.create')] : null,
            $drafts ? ['paper-airplane', "{$drafts} draft ".str('invoice')->plural($drafts).' not sent yet', route('vendor.invoices.index', ['status' => 'draft'])] : null,
            $this->stats['overdue'] ? ['exclamation-triangle', $this->stats['overdue'].' overdue '.str('invoice')->plural($this->stats['overdue']), route('vendor.invoices.index', ['status' => 'overdue'])] : null,
        ]));
    }

    #[Computed]
    public function currency(): string
    {
        return auth()->user()->vendor?->currency ?: config('accounting.currency', 'KES');
    }

    #[Computed]
    public function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
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
                    'borderColor' => '#14b8a6',
                    'backgroundColor' => 'rgba(20,184,166,0.12)',
                    'pointBackgroundColor' => '#14b8a6',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Invoiced',
                    'data' => $d['billed'],
                    'borderColor' => '#0e7c86',
                    'backgroundColor' => 'rgba(14,124,134,0.06)',
                    'pointBackgroundColor' => '#0e7c86',
                    'borderDash' => [5, 5],
                    'tension' => 0.35,
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
                    'backgroundColor' => 'rgba(20,184,166,0.75)',
                    'hoverBackgroundColor' => '#0e7c86',
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
                    // draft, sent, paid, overdue, void, written off
                    'backgroundColor' => ['#a1a1aa', '#0e7c86', '#14b8a6', '#e11d48', '#d4d4d8', '#f97316'],
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

    {{-- ─── Welcome banner ─── --}}
    <section class="relative isolate overflow-hidden rounded-2xl bg-linear-to-br from-brand-900 via-brand-800 to-brand-600 p-6 text-white shadow-lg shadow-brand-900/10 sm:p-8">
        <div class="pointer-events-none absolute -right-24 -top-24 -z-10 size-72 rounded-full bg-brand-400/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 left-1/3 -z-10 size-72 rounded-full bg-brand-500/20 blur-3xl"></div>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-200">{{ now()->format('l, j F Y') }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ $this->greeting }}, {{ str(auth()->user()->name)->before(' ') }}
                </h1>
                <p class="mt-2 max-w-xl text-sm text-brand-100/90">
                    {{ auth()->user()->vendor?->name }} ·
                    {{ number_format($this->stats['active_meters']) }} active {{ str('meter')->plural($this->stats['active_meters']) }}
                    serving {{ number_format($this->stats['clients']) }} {{ str('client')->plural($this->stats['clients']) }}.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('vendor.readings.create') }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-400 px-4 py-2.5 text-sm font-semibold text-brand-950 shadow-sm transition hover:bg-brand-300">
                    <flux:icon.plus variant="micro" class="size-4" /> New reading
                </a>
                <a href="{{ route('vendor.invoices.create') }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-xl border border-white/25 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                    <flux:icon.document-text variant="micro" class="size-4" /> Bill a reading
                </a>
                <a href="{{ route('vendor.payments.create') }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-xl border border-white/25 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                    <flux:icon.banknotes variant="micro" class="size-4" /> Record payment
                </a>
            </div>
        </div>
    </section>

    {{-- ─── Needs attention ─── --}}
    @if ($this->attention)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->attention as [$icon, $text, $href])
                <a href="{{ $href }}" wire:navigate wire:key="attention-{{ $loop->index }}"
                    class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3.5 py-1.5 text-sm font-medium text-amber-800 transition hover:border-amber-300 hover:bg-amber-100 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/15">
                    <flux:icon :name="$icon" variant="micro" class="size-4" />
                    {{ $text }}
                    <flux:icon.chevron-right variant="micro" class="size-3.5 opacity-60" />
                </a>
            @endforeach
        </div>
    @endif

    {{-- ─── KPIs ─── --}}
    @php
        $s = $this->stats;
        $kpis = [
            ['Clients', number_format($s['clients']), number_format($s['meters']).' '.str('meter')->plural($s['meters']), 'users', route('vendor.clients.index'), 'brand'],
            ['Readings', number_format($s['readings_30d']), 'in the last 30 days', 'beaker', route('vendor.readings.index'), 'sky'],
            ['Collected', $this->currency.' '.number_format($s['revenue_30d'], 2), number_format($s['payments_30d']).' '.str('payment')->plural($s['payments_30d']).' · 30 days', 'banknotes', route('vendor.payments.index'), 'emerald'],
            ['Outstanding', $this->currency.' '.number_format($s['unpaid'], 2), number_format($s['unpaid_count']).' unpaid '.str('invoice')->plural($s['unpaid_count']), 'scale', route('vendor.invoices.index', ['status' => 'sent']), 'amber'],
            ['Overdue', $this->currency.' '.number_format($s['overdue_amount'], 2), number_format($s['overdue']).' '.str('invoice')->plural($s['overdue']).' past due', 'exclamation-triangle', route('vendor.invoices.index', ['status' => 'overdue']), $s['overdue'] ? 'rose' : 'brand'],
        ];

        // Full class strings so Tailwind picks them up: card, icon chip, value.
        $tones = [
            'brand' => ['border-brand-200/70 bg-linear-to-br from-brand-50 via-white to-white hover:border-brand-300 dark:border-brand-500/20 dark:from-brand-500/15 dark:via-zinc-900 dark:to-zinc-900', 'bg-brand-500 text-white shadow-sm shadow-brand-500/30', 'text-brand-800 dark:text-brand-200'],
            'sky' => ['border-sky-200/70 bg-linear-to-br from-sky-50 via-white to-white hover:border-sky-300 dark:border-sky-500/20 dark:from-sky-500/15 dark:via-zinc-900 dark:to-zinc-900', 'bg-sky-500 text-white shadow-sm shadow-sky-500/30', 'text-sky-900 dark:text-sky-200'],
            'emerald' => ['border-emerald-200/70 bg-linear-to-br from-emerald-50 via-white to-white hover:border-emerald-300 dark:border-emerald-500/20 dark:from-emerald-500/15 dark:via-zinc-900 dark:to-zinc-900', 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/30', 'text-emerald-800 dark:text-emerald-200'],
            'amber' => ['border-amber-200/70 bg-linear-to-br from-amber-50 via-white to-white hover:border-amber-300 dark:border-amber-500/20 dark:from-amber-500/15 dark:via-zinc-900 dark:to-zinc-900', 'bg-amber-500 text-white shadow-sm shadow-amber-500/30', 'text-amber-900 dark:text-amber-200'],
            'rose' => ['border-rose-200/70 bg-linear-to-br from-rose-50 via-white to-white hover:border-rose-300 dark:border-rose-500/20 dark:from-rose-500/15 dark:via-zinc-900 dark:to-zinc-900', 'bg-rose-500 text-white shadow-sm shadow-rose-500/30', 'text-rose-700 dark:text-rose-300'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($kpis as [$label, $value, $sub, $icon, $href, $tone])
            @php [$cardClass, $chipClass, $valueClass] = $tones[$tone]; @endphp
            <a href="{{ $href }}" wire:navigate wire:key="kpi-{{ $loop->index }}"
                class="group relative overflow-hidden rounded-2xl border p-5 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-zinc-900/5 {{ $cardClass }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
                    <span class="flex size-9 items-center justify-center rounded-xl {{ $chipClass }}">
                        <flux:icon :name="$icon" variant="mini" class="size-5" />
                    </span>
                </div>
                <div class="mt-3 truncate font-mono text-xl font-semibold tracking-tight {{ $valueClass }}">
                    {{ $value }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $sub }}</div>
            </a>
        @endforeach
    </div>

    {{-- ─── Revenue trend ─── --}}
    <section class="rounded-2xl border border-brand-100 bg-linear-to-b from-brand-50/70 to-white p-5 dark:border-brand-500/15 dark:from-brand-500/10 dark:to-zinc-900 sm:p-6">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <flux:icon.presentation-chart-line variant="mini" class="size-5" />
                </span>
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Revenue vs invoiced</h2>
                    <p class="text-xs text-zinc-500">Last 12 months · solid = payments received, dashed = invoices issued</p>
                </div>
            </div>
            <a href="{{ route('vendor.reports.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                Detailed report <flux:icon.arrow-right variant="micro" class="size-4" />
            </a>
        </div>
        <x-chart :data="$this->revenueChart" height="18rem" :legend="true" />
    </section>

    {{-- ─── Consumption + status mix ─── --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="rounded-2xl border border-sky-100 bg-linear-to-b from-sky-50/70 to-white p-5 dark:border-sky-500/15 dark:from-sky-500/10 dark:to-zinc-900 sm:p-6 lg:col-span-2">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400">
                    <flux:icon.chart-bar variant="mini" class="size-5" />
                </span>
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Water consumption</h2>
                    <p class="text-xs text-zinc-500">Approved readings, m³ per month</p>
                </div>
            </div>
            <x-chart :data="$this->consumptionChart" height="16rem" />
        </section>

        <section class="rounded-2xl border border-brand-100 bg-linear-to-b from-brand-50/70 to-white p-5 dark:border-brand-500/15 dark:from-brand-500/10 dark:to-zinc-900 sm:p-6">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <flux:icon.chart-pie variant="mini" class="size-5" />
                </span>
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Invoice status</h2>
                    <p class="text-xs text-zinc-500">All invoices to date</p>
                </div>
            </div>
            <x-chart :data="$this->statusChart" height="16rem" :legend="true" />
        </section>
    </div>

    {{-- ─── Recent activity ─── --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-brand-100 bg-linear-to-b from-brand-50/60 to-white dark:border-brand-500/15 dark:from-brand-500/10 dark:to-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200/70 px-5 py-4 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                        <flux:icon.document-text variant="micro" class="size-4" />
                    </span>
                    <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Recent invoices</h2>
                </div>
                <a href="{{ route('vendor.invoices.index') }}" wire:navigate class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">View all</a>
            </div>

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->recentInvoices as $inv)
                    <li wire:key="inv-{{ $inv->id }}">
                        <a href="{{ route('vendor.invoices.show', $inv) }}" wire:navigate
                            class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-brand-50/50 dark:hover:bg-brand-500/5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $inv->client->name }}</p>
                                <p class="font-mono text-xs text-zinc-500">{{ $inv->number }} · {{ $inv->issued_at?->format('d M Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-mono text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($inv->total, 2) }}</p>
                                <flux:badge :color="$inv->status->color()" size="sm" class="mt-1">{{ $inv->status->label() }}</flux:badge>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-zinc-500">
                        No invoices yet.
                        <a href="{{ route('vendor.invoices.create') }}" wire:navigate class="font-medium text-brand-600 dark:text-brand-400">Bill your first reading</a>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="overflow-hidden rounded-2xl border border-emerald-100 bg-linear-to-b from-emerald-50/60 to-white dark:border-emerald-500/15 dark:from-emerald-500/10 dark:to-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200/70 px-5 py-4 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                        <flux:icon.banknotes variant="micro" class="size-4" />
                    </span>
                    <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Recent payments</h2>
                </div>
                <a href="{{ route('vendor.payments.index') }}" wire:navigate class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">View all</a>
            </div>

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->recentPayments as $p)
                    <li wire:key="pay-{{ $p->id }}" class="flex items-center gap-4 px-5 py-3.5">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                            {{ str($p->client->name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $p->client->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $p->method->label() }} · {{ $p->paid_at->format('d M Y') }}</p>
                        </div>
                        <p class="font-mono text-sm font-semibold text-brand-600 dark:text-brand-400">+{{ number_format($p->amount, 2) }}</p>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-zinc-500">No payments yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
