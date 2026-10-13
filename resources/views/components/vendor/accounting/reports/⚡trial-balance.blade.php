<?php

use App\Exports\Accounting\TrialBalanceExport;
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

new #[Layout('layouts.vendor'), Title('Trial Balance')] class extends Component {
    use UsesAccounting;

    #[Url(as: 'as_of', history: true)]
    public string $asOf = '';

    public function mount(): void
    {
        $this->bootAccounting();
        $this->asOf = $this->asOf ?: now()->toDateString();
    }

    #[Computed]
    public function report(): array
    {
        return app(ReportService::class)->trialBalance($this->vendorId(), $this->dateOr($this->asOf, CarbonImmutable::now()));
    }

    public function export()
    {
        Flux::toast(variant: 'success', heading: 'Export ready', text: 'Trial balance downloading.');

        return Excel::download(new TrialBalanceExport($this->report), "trial-balance-{$this->report['as_of']->toDateString()}.xlsx");
    }
};
?>

<div class="p-4 lg:p-6 max-w-5xl space-y-4">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="scale" title="Trial balance" subtitle="Every account's balance at a date. Debits always equal credits." />
        <div class="flex items-end gap-3">
            <flux:input type="date" wire:model.live="asOf" label="As of" class="w-44" />
            <flux:button wire:click="export" icon="arrow-down-tray" variant="filled">Export</flux:button>
        </div>
    </flux:card>

    @unless ($this->report['balanced'])
        <flux:callout variant="danger" icon="exclamation-triangle">The trial balance does not balance — please contact support.</flux:callout>
    @endunless

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Code</flux:table.column>
            <flux:table.column>Account</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column align="end">Debit</flux:table.column>
            <flux:table.column align="end">Credit</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->report['rows'] as $r)
                <flux:table.row :key="'tb-' . $r['account_id']">
                    <flux:table.cell class="font-mono">{{ $r['code'] }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link href="{{ route('vendor.accounting.reports.general-ledger', ['account' => $r['account_id'], 'to' => $asOf]) }}" wire:navigate>{{ $r['name'] }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell><x-accounting.type-badge :type="$r['type']" /></flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r['debit']" blank-zero /></flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$r['credit']" blank-zero /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500 py-10">Nothing posted yet.</flux:table.cell>
                </flux:table.row>
            @endforelse
            <flux:table.row>
                <flux:table.cell colspan="3" class="font-semibold">Total</flux:table.cell>
                <flux:table.cell align="end"><x-accounting.amount :value="$this->report['total_debit']" strong /></flux:table.cell>
                <flux:table.cell align="end"><x-accounting.amount :value="$this->report['total_credit']" strong /></flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>
</div>
