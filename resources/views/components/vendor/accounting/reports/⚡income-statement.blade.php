<?php

use App\Exports\Accounting\IncomeStatementExport;
use App\Livewire\Concerns\UsesAccounting;
use App\Services\Accounting\ReportService;
use App\Support\Money\MoneyMath;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.vendor'), Title('Income Statement')] class extends Component {
    use UsesAccounting;

    #[Url(history: true)]
    public string $from = '';
    #[Url(history: true)]
    public string $to = '';
    #[Url(history: true)]
    public bool $compare = false;

    public function mount(): void
    {
        $this->bootAccounting();
        $this->from = $this->from ?: now()->startOfYear()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    protected function range(): array
    {
        $from = $this->dateOr($this->from, CarbonImmutable::now()->startOfYear());
        $to = $this->dateOr($this->to, CarbonImmutable::now());

        return $to->lt($from) ? [$to, $from] : [$from, $to];
    }

    #[Computed]
    public function report(): array
    {
        [$from, $to] = $this->range();

        return app(ReportService::class)->incomeStatement($this->vendorId(), $from, $to);
    }

    /** Same-length period immediately before the selected one. */
    #[Computed]
    public function prior(): ?array
    {
        if (! $this->compare) {
            return null;
        }

        [$from, $to] = $this->range();
        $days = $from->diffInDays($to);
        $priorTo = $from->subDay();

        return app(ReportService::class)->incomeStatement($this->vendorId(), $priorTo->subDays((int) $days), $priorTo);
    }

    /** Prior-period amount for an account id within a section. */
    public function priorAmount(string $section, ?int $accountId): string
    {
        $row = collect($this->prior[$section] ?? [])->firstWhere('account_id', $accountId);

        return $row['amount'] ?? '0.00';
    }

    public function export()
    {
        [$from, $to] = $this->range();
        Flux::toast(variant: 'success', heading: 'Export ready', text: 'Income statement downloading.');

        return Excel::download(new IncomeStatementExport($this->report), "income-statement-{$from->toDateString()}-to-{$to->toDateString()}.xlsx");
    }
};
?>

<div class="p-4 lg:p-6 max-w-5xl space-y-4">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="presentation-chart-line" title="Income statement" subtitle="Revenue less expenses for the period (profit & loss)." />
        <div class="flex flex-wrap items-end gap-3">
            <x-accounting.date-range />
            <flux:checkbox wire:model.live="compare" label="Compare with prior period" />
            <flux:button wire:click="export" icon="arrow-down-tray" variant="filled">Export</flux:button>
        </div>
    </flux:card>

    @php $r = $this->report; $p = $this->prior; @endphp

    @foreach ([['revenue', 'Revenue', 'total_revenue'], ['expenses', 'Expenses', 'total_expenses']] as [$key, $label, $totalKey])
        <flux:table wire:key="sec-{{ $key }}">
            <flux:table.columns>
                <flux:table.column>{{ $label }}</flux:table.column>
                <flux:table.column align="end">This period</flux:table.column>
                @if ($p)
                    <flux:table.column align="end">Prior period</flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($r[$key] as $row)
                    <flux:table.row :key="$key . '-' . $row['account_id']">
                        <flux:table.cell><span class="font-mono">{{ $row['code'] }}</span> {{ $row['name'] }}</flux:table.cell>
                        <flux:table.cell align="end"><x-accounting.amount :value="$row['amount']" /></flux:table.cell>
                        @if ($p)
                            <flux:table.cell align="end"><x-accounting.amount :value="$this->priorAmount($key, $row['account_id'])" class="text-zinc-500" /></flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-zinc-500">No {{ strtolower($label) }} in this period.</flux:table.cell>
                    </flux:table.row>
                @endforelse
                <flux:table.row>
                    <flux:table.cell class="font-semibold">Total {{ strtolower($label) }}</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r[$totalKey]" strong /></flux:table.cell>
                    @if ($p)
                        <flux:table.cell align="end"><x-accounting.amount :value="$p[$totalKey]" strong /></flux:table.cell>
                    @endif
                </flux:table.row>
            </flux:table.rows>
        </flux:table>
    @endforeach

    <flux:card class="flex items-center justify-between">
        <flux:heading size="lg">Net income</flux:heading>
        <div class="flex gap-8 text-lg">
            <x-accounting.amount :value="$r['net_income']" strong />
            @if ($p)
                <span class="text-sm text-zinc-500 self-center">prior <x-accounting.amount :value="$p['net_income']" /></span>
            @endif
        </div>
    </flux:card>
</div>
