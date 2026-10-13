<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\UsesAccounting;
use App\Models\Asset;
use App\Models\Invoice;
use App\Models\Liability;
use App\Models\OpeningBalance;
use App\Services\Accounting\Exceptions\AccountingException;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\Postings\OpeningBalanceEntry;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Multi-step wizard: what the business owned and owed on the day it starts
 * using the ledger. Posts ONE balanced entry; Owner's Equity is the plug.
 */
new #[Layout('layouts.vendor'), Title('Opening Balances')] class extends Component {
    use UsesAccounting;

    public int $step = 1;
    public string $as_of_date = '';
    public string $cash = '0';
    public string $receivables = '0';
    public string $fixed_assets = '0';
    public string $accumulated_depreciation = '0';
    public string $payables = '0';
    public string $notes = '';

    public function mount(): void
    {
        $this->bootAccounting();
        $this->as_of_date = now()->startOfYear()->subDay()->toDateString();
        $this->prefill();
    }

    #[Computed]
    public function existing(): ?OpeningBalance
    {
        return OpeningBalance::with('journalEntry')->first();
    }

    /** Suggest figures from what the app already knows. */
    public function prefill(): void
    {
        $this->receivables = MoneyMath::str((string) (Invoice::whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->sum(DB::raw('total - amount_paid')) ?: '0'));

        $assets = Asset::where('status', 'active')->get();
        $this->fixed_assets = MoneyMath::sumColumn($assets, 'purchase_cost');
        $this->accumulated_depreciation = MoneyMath::sumColumn(
            $assets->map(fn ($a) => ['v' => number_format($a->accumulatedDepreciation(), 2, '.', '')]), 'v'
        );

        $this->payables = MoneyMath::sumColumn(
            Liability::where('status', 'active')->get()->map(fn ($l) => ['v' => number_format($l->outstanding(), 2, '.', '')]), 'v'
        );
    }

    protected function amountRule(): array
    {
        return ['required', 'numeric', 'min:0', 'decimal:0,2'];
    }

    public function next(): void
    {
        $rules = match ($this->step) {
            1 => ['as_of_date' => ['required', 'date', 'before_or_equal:today']],
            2 => ['cash' => $this->amountRule()],
            3 => ['receivables' => $this->amountRule()],
            4 => ['fixed_assets' => $this->amountRule(), 'accumulated_depreciation' => array_merge($this->amountRule(), ['lte:fixed_assets'])],
            5 => ['payables' => $this->amountRule()],
            default => [],
        };
        $this->validate($rules);
        $this->step = min(6, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /** Equity = assets − liabilities (may be negative: then it's a debit). */
    #[Computed]
    public function equity(): string
    {
        $assets = MoneyMath::add($this->n($this->cash), $this->n($this->receivables), $this->n($this->fixed_assets));
        $assets = MoneyMath::sub($assets, $this->n($this->accumulated_depreciation));

        return MoneyMath::sub($assets, $this->n($this->payables));
    }

    protected function n(string $v): string
    {
        return is_numeric(trim($v)) ? MoneyMath::str(trim($v)) : '0.00';
    }

    public function post(LedgerService $ledger): void
    {
        $this->authorize('accounting.post');

        if ($this->existing?->is_posted) {
            Flux::toast(variant: 'danger', heading: 'Already posted', text: 'Opening balances can only be posted once. Reverse the entry first to redo them.');
            return;
        }

        $this->validate([
            'as_of_date' => ['required', 'date', 'before_or_equal:today'],
            'cash' => $this->amountRule(), 'receivables' => $this->amountRule(),
            'fixed_assets' => $this->amountRule(), 'accumulated_depreciation' => array_merge($this->amountRule(), ['lte:fixed_assets']),
            'payables' => $this->amountRule(), 'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $c = fn (string $key) => config("accounting.accounts.{$key}");
        $equity = $this->equity;
        $lines = [
            ['account_code' => $c('cash'), 'debit' => $this->n($this->cash), 'credit' => '0'],
            ['account_code' => $c('receivables'), 'debit' => $this->n($this->receivables), 'credit' => '0'],
            ['account_code' => $c('fixed_assets'), 'debit' => $this->n($this->fixed_assets), 'credit' => '0'],
            ['account_code' => $c('accumulated_depreciation'), 'debit' => '0', 'credit' => $this->n($this->accumulated_depreciation)],
            ['account_code' => $c('payables'), 'debit' => '0', 'credit' => $this->n($this->payables)],
            MoneyMath::isNegative($equity)
                ? ['account_code' => $c('owners_equity'), 'debit' => MoneyMath::abs($equity), 'credit' => '0']
                : ['account_code' => $c('owners_equity'), 'debit' => '0', 'credit' => $equity],
        ];

        try {
            DB::transaction(function () use ($ledger, $lines) {
                $opening = OpeningBalance::lockForUpdate()->first()
                    ?? OpeningBalance::create(['as_of_date' => $this->as_of_date]);

                if ($opening->is_posted) {
                    throw new AccountingException('Opening balances have already been posted.');
                }

                $opening->update(['as_of_date' => $this->as_of_date, 'notes' => $this->notes ?: null]);
                $entry = $ledger->postEvent(new OpeningBalanceEntry($opening->fresh(), $lines));
                $opening->update(['is_posted' => true, 'journal_entry_id' => $entry->id]);
            });
        } catch (AccountingException $e) {
            Flux::toast(variant: 'danger', heading: 'Could not post', text: $e->getMessage());
            return;
        }

        unset($this->existing);
        Flux::toast(variant: 'success', heading: 'Opening balances posted', text: 'Your books now start from ' . \Carbon\Carbon::parse($this->as_of_date)->format('d M Y') . '.');
    }
};
?>

<div class="p-4 lg:p-6 max-w-3xl space-y-4">
    <flux:card class="space-y-6">
        <x-accounting.card-header icon="flag" title="Opening balances"
            subtitle="What the business owned and owed on the day it starts using the ledger. Posted once, as a single balanced entry." />

        @if ($this->existing?->is_posted)
            <flux:callout variant="success" icon="check-circle">
                Opening balances as of <strong>{{ $this->existing->as_of_date->format('d M Y') }}</strong> were posted in
                <flux:link href="{{ route('vendor.accounting.journal.show', $this->existing->journal_entry_id) }}" wire:navigate>
                    entry #{{ $this->existing->journal_entry_id }}</flux:link>.
                To change them, reverse that entry — this wizard then opens again.
            </flux:callout>
        @else
            {{-- Step indicator --}}
            <ol class="flex flex-wrap gap-2 text-xs">
                @foreach (['Date', 'Cash', 'Receivables', 'Fixed assets', 'Payables', 'Review'] as $i => $label)
                    <li wire:key="step-{{ $i }}" class="flex items-center gap-1.5 rounded-full px-2.5 py-1 {{ $step === $i + 1 ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : ($step > $i + 1 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800') }}">
                        <span class="font-bold">{{ $i + 1 }}</span> {{ $label }}
                    </li>
                @endforeach
            </ol>

            <div class="min-h-32">
                @if ($step === 1)
                    <flux:input type="date" wire:model="as_of_date" label="Opening date"
                        description="Usually the last day before you start recording in the app (e.g. 31 Dec)." />
                @elseif ($step === 2)
                    <flux:input wire:model="cash" label="Cash & bank balance (1000)" inputmode="decimal" class="font-mono"
                        description="Total of cash on hand, M-Pesa and bank accounts on the opening date." />
                @elseif ($step === 3)
                    <flux:input wire:model="receivables" label="Customers owe you (1100)" inputmode="decimal" class="font-mono"
                        description="Pre-filled from unpaid sent/overdue invoices in the app." />
                @elseif ($step === 4)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="fixed_assets" label="Meters & equipment at cost (1500)" inputmode="decimal" class="font-mono" />
                        <flux:input wire:model="accumulated_depreciation" label="Accumulated depreciation (1510)" inputmode="decimal" class="font-mono" />
                    </div>
                    <flux:text class="text-xs mt-2">Pre-filled from your active assets register.</flux:text>
                @elseif ($step === 5)
                    <flux:input wire:model="payables" label="You owe suppliers & lenders (2000)" inputmode="decimal" class="font-mono"
                        description="Pre-filled from active liabilities." />
                @else
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-800 text-sm">
                        @foreach ([
                            ['1000 Cash / Bank', $cash, 'Dr'], ['1100 Accounts Receivable', $receivables, 'Dr'],
                            ['1500 Meters & Equipment', $fixed_assets, 'Dr'], ['1510 Accumulated Depreciation', $accumulated_depreciation, 'Cr'],
                            ['2000 Accounts Payable', $payables, 'Cr'],
                        ] as [$label, $value, $side])
                            <div wire:key="rv-{{ $loop->index }}" class="flex justify-between px-4 py-2">
                                <span>{{ $label }} <span class="text-xs text-zinc-500">{{ $side }}</span></span>
                                <x-accounting.amount :value="is_numeric($value) ? $value : '0'" />
                            </div>
                        @endforeach
                        <div class="flex justify-between px-4 py-2 bg-brand-50/60 dark:bg-brand-500/10 font-semibold">
                            <span>3000 Owner's Equity <span class="text-xs text-zinc-500">{{ \App\Support\Money\MoneyMath::isNegative($this->equity) ? 'Dr' : 'Cr' }} · balancing figure</span></span>
                            <x-accounting.amount :value="$this->equity" strong />
                        </div>
                    </div>
                    <flux:textarea wire:model="notes" label="Notes (optional)" rows="2" class="mt-4" />
                @endif
            </div>

            <div class="flex justify-between">
                <flux:button variant="ghost" wire:click="back" :disabled="$step === 1" icon="arrow-left">Back</flux:button>
                @if ($step < 6)
                    <flux:button variant="primary" wire:click="next" icon-trailing="arrow-right">Next</flux:button>
                @else
                    @can('accounting.post')
                        <flux:button variant="primary" wire:click="post" wire:confirm="Post opening balances? This can only be done once." icon="check">
                            Post opening balances
                        </flux:button>
                    @endcan
                @endif
            </div>
        @endif
    </flux:card>
</div>
