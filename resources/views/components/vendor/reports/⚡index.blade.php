<?php

use App\Services\VendorAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Reports')] class extends Component {
    #[Url(history: true)]
    public string $range = '12m'; // 3m | 6m | 12m | ytd

    #[Computed]
    public function analytics(): VendorAnalytics
    {
        return new VendorAnalytics(auth()->user()->vendor_id);
    }

    #[Computed]
    public function months(): int
    {
        return match ($this->range) {
            '3m' => 3,
            '6m' => 6,
            'ytd' => now()->month,
            default => 12,
        };
    }

    #[Computed]
    public function revenueChart(): array
    {
        $d = $this->analytics->revenueTrend($this->months);

        return [
            'type' => 'line',
            'labels' => $d['labels'],
            'datasets' => [['label' => 'Paid', 'data' => $d['revenue'], 'borderColor' => '#059669', 'backgroundColor' => 'rgba(5,150,105,0.08)', 'fill' => true], ['label' => 'Invoiced', 'data' => $d['billed'], 'borderColor' => '#2563eb', 'backgroundColor' => 'rgba(37,99,235,0.06)', 'borderDash' => [4, 4], 'fill' => false]],
        ];
    }

    #[Computed]
    public function consumptionChart(): array
    {
        $d = $this->analytics->consumptionTrend($this->months);

        return [
            'type' => 'bar',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'label' => 'm³',
                    'data' => $d['values'],
                    'backgroundColor' => 'rgba(37,99,235,0.7)',
                    'borderRadius' => 6,
                ],
            ],
        ];
    }

    #[Computed]
    public function agingChart(): array
    {
        $d = $this->analytics->agingBuckets();

        return [
            'type' => 'bar',
            'labels' => $d['labels'],
            'datasets' => [
                [
                    'label' => 'Receivables',
                    'data' => $d['values'],
                    'backgroundColor' => ['#059669', '#f59e0b', '#f97316', '#ef4444', '#991b1b'],
                    'borderRadius' => 6,
                ],
            ],
        ];
    }

    #[Computed]
    public function topConsumers(): array
    {
        return $this->analytics->topConsumers(10);
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Reports</h1>
            <p class="text-sm text-zinc-500 mt-1">Revenue, consumption, and receivables at a glance.</p>
        </div>

        <flux:select wire:model.live="range" class="w-full sm:w-40">
            <flux:select.option value="3m">Last 3 months</flux:select.option>
            <flux:select.option value="6m">Last 6 months</flux:select.option>
            <flux:select.option value="12m">Last 12 months</flux:select.option>
            <flux:select.option value="ytd">Year to date</flux:select.option>
        </flux:select>
    </div>

    {{-- Revenue --}}
    <flux:card>
        <flux:heading size="sm" class="mb-4">Revenue vs. Invoiced</flux:heading>
        <x-chart :data="$this->revenueChart" height="20rem" :legend="true" />
    </flux:card>

    {{-- Consumption + Aging --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card>
            <flux:heading size="sm" class="mb-4">Consumption (m³)</flux:heading>
            <x-chart :data="$this->consumptionChart" height="16rem" />
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="mb-4">Receivables Aging</flux:heading>
            <x-chart :data="$this->agingChart" height="16rem" />
        </flux:card>
    </div>

    {{-- Top consumers --}}
    <flux:card class="p-0 overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="sm">Top 10 consumers</flux:heading>
        </div>
        <flux:table class="p-2">
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>Client</flux:table.column>
                <flux:table.column>Account</flux:table.column>
                <flux:table.column align="end">Total consumption</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->topConsumers as $i => $row)
                    <flux:table.row :key="$row['account_no']">
                        <flux:table.cell class="text-zinc-500">{{ $i + 1 }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $row['name'] }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $row['account_no'] }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">
                            {{ number_format($row['total']) }} m³
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">No consumption data yet.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
