<?php

use App\Services\FinancialReports;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Financial Reports')] class extends Component {
    #[Url(history: true)]
    public string $period = 'this_month';
    #[Url(history: true)]
    public string $tab = 'pl'; // pl | balance | cashflow

    #[Computed]
    public function reports(): FinancialReports
    {
        return new FinancialReports(auth()->user()->vendor_id);
    }

    #[Computed]
    public function range(): array
    {
        return match ($this->period) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth(), 'This month'],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(), 'Last month'],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter(), 'This quarter'],
            'this_year' => [now()->startOfYear(), now()->endOfYear(), 'This year'],
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear(), 'Last year'],
            default => [now()->startOfMonth(), now()->endOfMonth(), 'This month'],
        };
    }

    #[Computed]
    public function incomeStatement(): array
    {
        [$from, $to] = $this->range;
        return $this->reports->incomeStatement($from, $to);
    }

    #[Computed]
    public function balanceSheet(): array
    {
        return $this->reports->balanceSheet();
    }

    #[Computed]
    public function cashFlowChart(): array
    {
        $d = $this->reports->cashFlowTrend(12);

        return [
            'type' => 'bar',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $d['rev'],
                    'backgroundColor' => 'rgba(5,150,105,0.75)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Expenses',
                    'data' => $d['exp'],
                    'backgroundColor' => 'rgba(225,29,72,0.75)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Net',
                    'data' => $d['net'],
                    'type' => 'line',
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    #[Computed]
    public function expenseMixChart(): array
    {
        [$from, $to] = $this->range;
        $d = $this->reports->expensesByCategory($from, $to);

        // Colour names from the seeder match Tailwind palette names.
        $colorMap = [
            'blue' => '#3b82f6',
            'violet' => '#8b5cf6',
            'amber' => '#f59e0b',
            'yellow' => '#eab308',
            'orange' => '#f97316',
            'rose' => '#f43f5e',
            'emerald' => '#10b981',
            'zinc' => '#71717a',
            'red' => '#ef4444',
            'slate' => '#64748b',
        ];

        $colors = array_map(fn($c) => $colorMap[$c] ?? '#71717a', $d['colors']);

        return [
            'type' => 'doughnut',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'data' => $d['values'],
                    'backgroundColor' => $colors ?: ['#71717a'],
                    'borderWidth' => 0,
                ],
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Financial Reports</h1>
            <p class="text-sm text-zinc-500 mt-1">Income statement, balance sheet, and cash flow.</p>
        </div>

        <flux:select wire:model.live="period" class="w-full sm:w-52">
            <flux:select.option value="this_month">This month</flux:select.option>
            <flux:select.option value="last_month">Last month</flux:select.option>
            <flux:select.option value="this_quarter">This quarter</flux:select.option>
            <flux:select.option value="this_year">This year</flux:select.option>
            <flux:select.option value="last_year">Last year</flux:select.option>
        </flux:select>
    </div>

    {{-- Tab selector --}}
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex gap-6">
            @foreach ([
        'pl' => 'Income Statement',
        'balance' => 'Balance Sheet',
        'cashflow' => 'Cash Flow',
    ] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="py-3 px-1 border-b-2 text-sm font-medium transition
                        {{ $tab === $key
                            ? 'border-brand-600 text-brand-600'
                            : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ═══════ Income Statement ═══════ --}}
    @if ($tab === 'pl')
        <div class="grid gap-6 lg:grid-cols-3">

            <flux:card class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">
                        Income Statement — {{ $this->range[2] }}
                    </flux:heading>
                    <span class="text-xs text-zinc-500">
                        {{ $this->range[0]->format('d M Y') }} → {{ $this->range[1]->format('d M Y') }}
                    </span>
                </div>

                @php $is = $this->incomeStatement; @endphp

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">Revenue</span>
                        <span class="font-mono text-accent-600 font-semibold">
                            {{ number_format($is['revenue'], 2) }}
                        </span>
                    </div>

                    <div class="pt-3 pb-1 text-xs uppercase tracking-wide text-zinc-500 font-semibold">
                        Expenses
                    </div>

                    @forelse ($is['expense_lines'] as $line)
                        <div class="flex justify-between py-1.5 text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $line['name'] }}</span>
                            <span class="font-mono text-zinc-700 dark:text-zinc-300">
                                {{ number_format($line['amount'], 2) }}
                            </span>
                        </div>
                    @empty
                        <div class="text-sm text-zinc-500 py-2">No expenses in this period.</div>
                    @endforelse

                    <div class="flex justify-between py-2 border-t border-zinc-200 dark:border-zinc-700 font-medium">
                        <span>Total Expenses</span>
                        <span class="font-mono text-rose-600">{{ number_format($is['expenses'], 2) }}</span>
                    </div>

                    <div
                        class="flex justify-between py-3 border-t-2 border-zinc-300 dark:border-zinc-600 text-base font-bold">
                        <span>Net {{ $is['net_profit'] >= 0 ? 'Profit' : 'Loss' }}</span>
                        <span class="font-mono {{ $is['net_profit'] >= 0 ? 'text-accent-600' : 'text-rose-600' }}">
                            {{ number_format(abs($is['net_profit']), 2) }}
                        </span>
                    </div>

                    @if ($is['revenue'] > 0)
                        <div class="text-xs text-zinc-500 text-right">
                            Profit margin: {{ $is['profit_margin'] }}%
                        </div>
                    @endif
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="sm" class="mb-4">Expense breakdown</flux:heading>
                <x-chart :data="$this->expenseMixChart" height="16rem" :legend="true" />
            </flux:card>
        </div>
    @endif

    {{-- ═══════ Balance Sheet ═══════ --}}
    @if ($tab === 'balance')
        @php $bs = $this->balanceSheet; @endphp

        <div class="text-center text-sm text-zinc-500">
            As of {{ $bs['as_of']->format('d M Y H:i') }}
        </div>

        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Assets --}}
            <flux:card class="space-y-4">
                <flux:heading size="sm">Assets</flux:heading>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-2">
                        <span class="text-zinc-600 dark:text-zinc-400">Cash on hand</span>
                        <span class="font-mono">{{ number_format($bs['cash'], 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-zinc-600 dark:text-zinc-400">Accounts receivable</span>
                        <span class="font-mono">{{ number_format($bs['receivables'], 2) }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-zinc-600 dark:text-zinc-400">Fixed assets (net)</span>
                        <span class="font-mono">{{ number_format($bs['fixed_assets'], 2) }}</span>
                    </div>

                    <div
                        class="flex justify-between py-3 border-t-2 border-zinc-300 dark:border-zinc-600 font-bold text-base">
                        <span>Total Assets</span>
                        <span class="font-mono text-accent-600">{{ number_format($bs['total_assets'], 2) }}</span>
                    </div>
                </div>
            </flux:card>

            {{-- Liabilities & Equity --}}
            <flux:card class="space-y-4">
                <flux:heading size="sm">Liabilities & Equity</flux:heading>

                <div class="space-y-2 text-sm">
                    <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold">
                        Liabilities
                    </div>
                    @forelse ($bs['liability_lines'] as $line)
                        <div class="flex justify-between py-1.5">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $line['name'] }}</span>
                            <span class="font-mono">{{ number_format($line['amount'], 2) }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-zinc-500 py-2">No active liabilities.</div>
                    @endforelse

                    <div class="flex justify-between py-2 border-t border-zinc-200 dark:border-zinc-700 font-medium">
                        <span>Total Liabilities</span>
                        <span class="font-mono text-rose-600">{{ number_format($bs['total_liabilities'], 2) }}</span>
                    </div>

                    <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold pt-4">
                        Equity
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-zinc-600 dark:text-zinc-400">Owner's equity</span>
                        <span class="font-mono">{{ number_format($bs['equity'], 2) }}</span>
                    </div>

                    <div
                        class="flex justify-between py-3 border-t-2 border-zinc-300 dark:border-zinc-600 font-bold text-base">
                        <span>Total Liabilities + Equity</span>
                        <span class="font-mono text-brand-600">
                            {{ number_format($bs['total_liabilities'] + $bs['equity'], 2) }}
                        </span>
                    </div>
                </div>
            </flux:card>
        </div>

        <div
            class="rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 p-4 text-xs text-zinc-600 dark:text-zinc-400">
            <strong>Note:</strong> This is a simplified cash-basis balance sheet. Cash flow is derived from
            recorded payments (in) and expenses (out). It does not include opening cash balances or
            non-cash equity adjustments. For full accrual accounting, export to CSV and hand to your accountant.
        </div>
    @endif

    {{-- ═══════ Cash Flow ═══════ --}}
    @if ($tab === 'cashflow')
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="sm">Cash flow — last 12 months</flux:heading>
                    <flux:text class="text-xs text-zinc-500">
                        Revenue (green), Expenses (red), Net (blue line)
                    </flux:text>
                </div>
            </div>
            <x-chart :data="$this->cashFlowChart" height="22rem" :legend="true" />
        </flux:card>

        @php
            $cf = $this->reports->cashFlowTrend(12);
            $totalRev = array_sum($cf['rev']);
            $totalExp = array_sum($cf['exp']);
            $totalNet = $totalRev - $totalExp;
        @endphp

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="app-card p-5">
                <span class="text-xs uppercase tracking-wide text-zinc-500">Total revenue (12m)</span>
                <div class="text-2xl font-semibold text-accent-600 mt-1 font-mono">
                    {{ number_format($totalRev, 2) }}
                </div>
            </div>
            <div class="app-card p-5">
                <span class="text-xs uppercase tracking-wide text-zinc-500">Total expenses (12m)</span>
                <div class="text-2xl font-semibold text-rose-600 mt-1 font-mono">
                    {{ number_format($totalExp, 2) }}
                </div>
            </div>
            <div class="app-card p-5">
                <span class="text-xs uppercase tracking-wide text-zinc-500">Net cash (12m)</span>
                <div
                    class="text-2xl font-semibold mt-1 font-mono {{ $totalNet >= 0 ? 'text-accent-600' : 'text-rose-600' }}">
                    {{ number_format($totalNet, 2) }}
                </div>
            </div>
        </div>
    @endif
</div>
