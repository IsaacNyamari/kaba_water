<?php

use App\Exports\Accounting\GeneralLedgerExport;
use App\Livewire\Concerns\UsesAccounting;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ReportService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.vendor'), Title('General Ledger')] class extends Component {
    use UsesAccounting;

    #[Url(history: true)]
    public ?int $account = null;
    #[Url(history: true)]
    public string $from = '';
    #[Url(history: true)]
    public string $to = '';

    public function mount(): void
    {
        $this->bootAccounting();
        $this->from = $this->from ?: now()->startOfYear()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
        $this->account ??= ChartOfAccount::orderBy('code')->value('id');
    }

    #[Computed]
    public function accounts()
    {
        return ChartOfAccount::withTrashed()->orderBy('code')->get(['id', 'code', 'name', 'is_active']);
    }

    #[Computed]
    public function report(): ?array
    {
        if (! $this->account || ! $this->accounts->firstWhere('id', $this->account)) {
            return null;
        }

        $from = $this->dateOr($this->from, CarbonImmutable::now()->startOfYear());
        $to = $this->dateOr($this->to, CarbonImmutable::now());
        [$from, $to] = $to->lt($from) ? [$to, $from] : [$from, $to];

        return app(ReportService::class)->generalLedger($this->vendorId(), $this->account, $from, $to);
    }

    public function export()
    {
        if (! $this->report) {
            return null;
        }

        Flux::toast(variant: 'success', heading: 'Export ready', text: 'General ledger downloading.');

        return Excel::download(new GeneralLedgerExport($this->report), "general-ledger-{$this->report['account']->code}.xlsx");
    }
};
?>

<div class="p-4 lg:p-6 max-w-6xl space-y-4">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="book-open" title="General ledger" subtitle="Every posting to one account, with a running balance." />
        <div class="flex flex-wrap items-end gap-3">
            <flux:select wire:model.live="account" label="Account" searchable class="w-72">
                @foreach ($this->accounts as $a)
                    <flux:select.option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}{{ $a->is_active ? '' : ' (inactive)' }}</flux:select.option>
                @endforeach
            </flux:select>
            <x-accounting.date-range />
            <flux:button wire:click="export" icon="arrow-down-tray" variant="filled">Export</flux:button>
        </div>
    </flux:card>

    @if ($r = $this->report)
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Entry</flux:table.column>
                <flux:table.column>Description</flux:table.column>
                <flux:table.column align="end">Debit</flux:table.column>
                <flux:table.column align="end">Credit</flux:table.column>
                <flux:table.column align="end">Balance ({{ $r['account']->normal_side->label() }})</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-zinc-500">Opening balance</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r['opening']" /></flux:table.cell>
                </flux:table.row>
                @foreach ($r['rows'] as $i => $l)
                    <flux:table.row :key="'gl-' . $i">
                        <flux:table.cell class="whitespace-nowrap">{{ \Carbon\Carbon::parse($l['date'])->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell class="font-mono">
                            <flux:link href="{{ route('vendor.accounting.journal.show', $l['entry_id']) }}" wire:navigate>#{{ $l['entry_id'] }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $l['description'] }} <span class="text-xs text-zinc-500">{{ $l['reference'] }}</span></flux:table.cell>
                        <flux:table.cell align="end"><x-accounting.amount :value="$l['debit']" blank-zero /></flux:table.cell>
                        <flux:table.cell align="end"><x-accounting.amount :value="$l['credit']" blank-zero /></flux:table.cell>
                        <flux:table.cell align="end"><x-accounting.amount :value="$l['balance']" /></flux:table.cell>
                    </flux:table.row>
                @endforeach
                <flux:table.row>
                    <flux:table.cell colspan="3" class="font-semibold">Totals / closing balance</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r['total_debit']" strong /></flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r['total_credit']" strong /></flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r['closing']" strong /></flux:table.cell>
                </flux:table.row>
            </flux:table.rows>
        </flux:table>
    @else
        <flux:callout icon="information-circle">Choose an account.</flux:callout>
    @endif
</div>
