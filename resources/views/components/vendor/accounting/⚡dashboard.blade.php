<?php

use App\Livewire\Concerns\UsesAccounting;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\FiscalPeriodService;
use App\Services\Accounting\ReportService;
use App\Support\Money\MoneyMath;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Accounting overview — the landing page for accountants and staff
 * (workspace owners can open it from the Accounting menu).
 */
new #[Layout('layouts.vendor'), Title('Accounting')] class extends Component {
    use UsesAccounting;

    public function mount(): void
    {
        $this->bootAccounting();
    }

    /** Balance of an account by code, on its normal side, as of today. */
    protected function balance(string $key): string
    {
        $account = ChartOfAccount::where('code', config("accounting.accounts.{$key}"))->first();

        return $account ? MoneyMath::str($account->balanceAsOf(now())) : '0.00';
    }

    #[Computed]
    public function kpis(): array
    {
        $reports = app(ReportService::class);
        $today = CarbonImmutable::now();
        $month = $reports->incomeStatement($this->vendorId(), $today->startOfMonth(), $today);
        $year = $reports->incomeStatement($this->vendorId(), $today->startOfYear(), $today);

        return [
            ['Cash & bank', $this->balance('cash'), 'banknotes', route('vendor.accounting.reports.general-ledger', ['account' => ChartOfAccount::where('code', config('accounting.accounts.cash'))->value('id')])],
            ['Receivables', $this->balance('receivables'), 'arrow-down-left', route('vendor.accounting.reports.balance-sheet')],
            ['Payables', $this->balance('payables'), 'arrow-up-right', route('vendor.accounting.reports.balance-sheet')],
            ['Net income (month)', $month['net_income'], 'presentation-chart-line', route('vendor.accounting.reports.income-statement', ['from' => $today->startOfMonth()->toDateString(), 'to' => $today->toDateString()])],
            ['Net income (year)', $year['net_income'], 'chart-bar', route('vendor.accounting.reports.income-statement', ['from' => $today->startOfYear()->toDateString(), 'to' => $today->toDateString()])],
        ];
    }

    #[Computed]
    public function period()
    {
        return app(FiscalPeriodService::class)->currentPeriod($this->vendorId());
    }

    #[Computed]
    public function recent()
    {
        return JournalEntry::posted()->with('lines')->orderByDesc('date')->orderByDesc('id')->limit(8)->get();
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">
    <flux:card>
        <x-accounting.card-header icon="calculator" title="Accounting overview"
            :subtitle="'Balances as of ' . now()->format('d M Y') . ($this->period ? ' · current period ' . $this->period->name . ($this->period->is_closed ? ' (closed)' : ' (open)') : ' · no fiscal periods set up yet')">
            <x-slot:actions>
                @can('accounting.post')
                    <flux:button href="{{ route('vendor.accounting.journal.create') }}" variant="primary" icon="plus" wire:navigate>Manual entry</flux:button>
                @endcan
                <flux:button href="{{ route('vendor.accounting.reports.trial-balance') }}" variant="filled" icon="scale" wire:navigate>Trial balance</flux:button>
            </x-slot:actions>
        </x-accounting.card-header>
    </flux:card>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($this->kpis as [$label, $value, $icon, $href])
            <flux:card wire:key="kpi-{{ $loop->index }}" href="{{ $href }}" class="space-y-1">
                <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-zinc-500">
                    <flux:icon :name="$icon" class="size-4" /> {{ $label }}
                </div>
                <div class="text-2xl"><x-accounting.amount :value="$value" strong /></div>
            </flux:card>
        @endforeach
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Recent entries</flux:table.column>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column align="end">Amount</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->recent as $e)
                <flux:table.row :key="$e->id">
                    <flux:table.cell>
                        <flux:link href="{{ route('vendor.accounting.journal.show', $e) }}" wire:navigate>#{{ $e->id }}</flux:link>
                        <span class="text-sm">{{ \Illuminate\Support\Str::limit($e->memo, 70) }}</span>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $e->date->format('d M Y') }}</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$e->total_debit" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center text-zinc-500 py-8">
                        Nothing posted yet. Start with the
                        <flux:link href="{{ route('vendor.accounting.opening-balances') }}" wire:navigate>opening balances</flux:link>.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
