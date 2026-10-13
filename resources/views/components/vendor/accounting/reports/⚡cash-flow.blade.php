<?php

use App\Exports\Accounting\CashFlowExport;
use App\Livewire\Concerns\UsesAccounting;
use App\Services\Accounting\ReportService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.vendor'), Title('Cash Flow')] class extends Component {
    use UsesAccounting;

    #[Url(history: true)]
    public string $from = '';
    #[Url(history: true)]
    public string $to = '';

    public function mount(): void
    {
        $this->bootAccounting();
        $this->from = $this->from ?: now()->startOfYear()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    #[Computed]
    public function report(): array
    {
        $from = $this->dateOr($this->from, CarbonImmutable::now()->startOfYear());
        $to = $this->dateOr($this->to, CarbonImmutable::now());
        [$from, $to] = $to->lt($from) ? [$to, $from] : [$from, $to];

        return app(ReportService::class)->cashFlow($this->vendorId(), $from, $to);
    }

    public function export()
    {
        Flux::toast(variant: 'success', heading: 'Export ready', text: 'Cash flow statement downloading.');

        return Excel::download(new CashFlowExport($this->report), "cash-flow-{$this->report['from']->toDateString()}-to-{$this->report['to']->toDateString()}.xlsx");
    }
};
?>

<div class="p-4 lg:p-6 max-w-4xl space-y-4">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="arrows-right-left" title="Cash flow statement" subtitle="Indirect method: from net income to the change in cash." />
        <div class="flex flex-wrap items-end gap-3">
            <x-accounting.date-range />
            <flux:button wire:click="export" icon="arrow-down-tray" variant="filled">Export</flux:button>
        </div>
    </flux:card>

    @php $r = $this->report; @endphp

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Operating activities</flux:table.column>
            <flux:table.column align="end">Amount</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            <flux:table.row>
                <flux:table.cell>Net income</flux:table.cell>
                <flux:table.cell align="end"><x-accounting.amount :value="$r['net_income']" /></flux:table.cell>
            </flux:table.row>
            @foreach ($r['adjustments'] as $row)
                <flux:table.row :key="'adj-' . $row['account_id']">
                    <flux:table.cell class="pl-6">Add back: {{ $row['name'] }} (non-cash)</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$row['amount']" /></flux:table.cell>
                </flux:table.row>
            @endforeach
            @foreach ($r['working_capital'] as $row)
                <flux:table.row :key="'wc-' . $row['account_id']">
                    <flux:table.cell class="pl-6">Change in {{ $row['name'] }}</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$row['amount']" /></flux:table.cell>
                </flux:table.row>
            @endforeach
            <flux:table.row>
                <flux:table.cell class="font-semibold">Net cash from operating activities</flux:table.cell>
                <flux:table.cell align="end"><x-accounting.amount :value="$r['operating']" strong /></flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>

    @foreach ([['investing', 'Investing activities', 'investing_total'], ['financing', 'Financing activities', 'financing_total']] as [$key, $label, $totalKey])
        <flux:table wire:key="cf-{{ $key }}">
            <flux:table.columns>
                <flux:table.column>{{ $label }}</flux:table.column>
                <flux:table.column align="end">Amount</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($r[$key] as $row)
                    <flux:table.row :key="$key . '-' . $row['account_id']">
                        <flux:table.cell class="pl-6">Change in {{ $row['name'] }}</flux:table.cell>
                        <flux:table.cell align="end"><x-accounting.amount :value="$row['amount']" /></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="2" class="text-zinc-500">None.</flux:table.cell></flux:table.row>
                @endforelse
                <flux:table.row>
                    <flux:table.cell class="font-semibold">Net cash from {{ strtolower($label) }}</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r[$totalKey]" strong /></flux:table.cell>
                </flux:table.row>
            </flux:table.rows>
        </flux:table>
    @endforeach

    <flux:card class="grid gap-2 sm:grid-cols-3 text-sm">
        <div>Opening cash <div class="text-lg"><x-accounting.amount :value="$r['opening_cash']" strong /></div></div>
        <div>Net change <div class="text-lg"><x-accounting.amount :value="$r['net_change']" strong /></div></div>
        <div>Closing cash <div class="text-lg"><x-accounting.amount :value="$r['closing_cash']" strong /></div></div>
        <div class="sm:col-span-3">
            @if ($r['ties'])
                <flux:badge color="emerald" icon="check-circle">Ties to the change in Cash / Bank</flux:badge>
            @else
                <flux:badge color="rose" icon="exclamation-triangle">Does not tie to the cash accounts — contact support</flux:badge>
            @endif
        </div>
    </flux:card>
</div>
