<?php

use App\Exports\Accounting\BalanceSheetExport;
use App\Livewire\Concerns\UsesAccounting;
use App\Services\Accounting\Exceptions\UnbalancedBalanceSheetException;
use App\Services\Accounting\ReportService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.vendor'), Title('Balance Sheet')] class extends Component {
    use UsesAccounting;

    #[Url(as: 'as_of', history: true)]
    public string $asOf = '';

    public function mount(): void
    {
        $this->bootAccounting();
        $this->asOf = $this->asOf ?: now()->toDateString();
    }

    /** @return array{report: ?array, error: ?string} */
    #[Computed]
    public function result(): array
    {
        try {
            return ['report' => app(ReportService::class)->balanceSheet($this->vendorId(), $this->dateOr($this->asOf, CarbonImmutable::now())), 'error' => null];
        } catch (UnbalancedBalanceSheetException $e) {
            // Show a clear error instead of wrong numbers (CLAUDE.md acceptance criteria).
            return ['report' => null, 'error' => $e->getMessage()];
        }
    }

    public function export()
    {
        if (! $this->result['report']) {
            Flux::toast(variant: 'danger', heading: 'Cannot export', text: $this->result['error']);
            return null;
        }

        Flux::toast(variant: 'success', heading: 'Export ready', text: 'Balance sheet downloading.');

        return Excel::download(new BalanceSheetExport($this->result['report']), "balance-sheet-{$this->asOf}.xlsx");
    }
};
?>

<div class="p-4 lg:p-6 max-w-5xl space-y-4">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="building-library" title="Balance sheet" subtitle="What the business owns and owes at a date. Assets = Liabilities + Equity." />
        <div class="flex items-end gap-3">
            <flux:input type="date" wire:model.live="asOf" label="As of" class="w-44" />
            <flux:button wire:click="export" icon="arrow-down-tray" variant="filled">Export</flux:button>
        </div>
    </flux:card>

    @if ($this->result['error'])
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>The balance sheet does not balance</flux:callout.heading>
            <flux:callout.text>{{ $this->result['error'] }}</flux:callout.text>
        </flux:callout>
    @else
        @php $r = $this->result['report']; @endphp
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ([['assets', 'Assets', 'total_assets'], ['liabilities', 'Liabilities', 'total_liabilities'], ['equity', 'Equity', 'total_equity']] as [$key, $label, $totalKey])
                <flux:table wire:key="bs-{{ $key }}" class="{{ $key === 'assets' ? 'lg:row-span-2' : '' }}">
                    <flux:table.columns>
                        <flux:table.column>{{ $label }}</flux:table.column>
                        <flux:table.column align="end">Balance</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($r[$key] as $row)
                            <flux:table.row :key="$key . '-' . ($row['account_id'] ?? 'earnings')">
                                <flux:table.cell><span class="font-mono">{{ $row['code'] }}</span> {{ $row['name'] }}</flux:table.cell>
                                <flux:table.cell align="end"><x-accounting.amount :value="$row['amount']" /></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="2" class="text-zinc-500">None.</flux:table.cell></flux:table.row>
                        @endforelse
                        <flux:table.row>
                            <flux:table.cell class="font-semibold">Total {{ strtolower($label) }}</flux:table.cell>
                            <flux:table.cell align="end"><x-accounting.amount :value="$r[$totalKey]" strong /></flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            @endforeach
        </div>

        <flux:card class="flex flex-wrap items-center justify-between gap-4">
            <span class="text-sm">Total assets <x-accounting.amount :value="$r['total_assets']" strong /></span>
            <flux:badge color="emerald" icon="check-circle">Balanced</flux:badge>
            <span class="text-sm">Liabilities + equity <x-accounting.amount :value="$r['total_liabilities_and_equity']" strong /></span>
        </flux:card>
    @endif
</div>
