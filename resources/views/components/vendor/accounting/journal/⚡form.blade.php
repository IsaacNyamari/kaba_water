<?php

use App\Livewire\Concerns\UsesAccounting;
use App\Models\ChartOfAccount;
use App\Services\Accounting\Exceptions\AccountingException;
use App\Services\Accounting\LedgerService;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Manual Journal Entry')] class extends Component {
    use UsesAccounting;

    public string $date = '';
    public string $reference = '';
    public string $memo = '';

    /** @var list<array{account_id: string, description: string, debit: string, credit: string}> */
    public array $lines = [];

    public function mount(): void
    {
        $this->bootAccounting('accounting.post');
        $this->date = now()->toDateString();
        $this->lines = [$this->blank(), $this->blank()];
    }

    protected function blank(): array
    {
        return ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blank();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        if (count($this->lines) < 2) {
            $this->lines[] = $this->blank();
        }
    }

    #[Computed]
    public function accounts()
    {
        return ChartOfAccount::active()->orderBy('code')->get(['id', 'code', 'name', 'type']);
    }

    /** Live totals for the balance indicator. Invalid numbers count as zero. */
    #[Computed]
    public function totals(): array
    {
        $safe = fn ($v) => is_numeric(trim((string) $v)) ? MoneyMath::str(trim((string) $v)) : '0.00';
        $dr = MoneyMath::sumColumn(array_map(fn ($l) => ['v' => $safe($l['debit'])], $this->lines), 'v');
        $cr = MoneyMath::sumColumn(array_map(fn ($l) => ['v' => $safe($l['credit'])], $this->lines), 'v');

        return ['debit' => $dr, 'credit' => $cr, 'difference' => MoneyMath::sub($dr, $cr), 'balanced' => MoneyMath::equals($dr, $cr) && ! MoneyMath::isZero($dr)];
    }

    public function post(LedgerService $ledger): void
    {
        $this->authorize('accounting.post');

        $this->validate([
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'memo' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ], [], ['lines.*.debit' => 'debit', 'lines.*.credit' => 'credit']);

        // Ignore completely empty rows; every other row needs an account.
        $rows = array_values(array_filter($this->lines, fn ($l) => filled($l['account_id']) || filled($l['debit']) || filled($l['credit'])));
        foreach ($rows as $i => $l) {
            if (! filled($l['account_id'])) {
                $this->addError('lines', 'Line ' . ($i + 1) . ' needs an account.');
                return;
            }
        }

        try {
            $entry = $ledger->post(
                array_map(fn ($l) => [
                    'account_id' => (int) $l['account_id'],
                    'debit' => filled($l['debit']) ? (string) $l['debit'] : '0',
                    'credit' => filled($l['credit']) ? (string) $l['credit'] : '0',
                    'description' => $l['description'] ?: null,
                ], $rows),
                [
                    'vendor_id' => $this->vendorId(),
                    'date' => $this->date,
                    'reference' => $this->reference ?: null,
                    'memo' => $this->memo,
                    'created_by' => auth()->id(),
                ],
            );
        } catch (AccountingException $e) {
            $this->addError('lines', $e->getMessage());
            return;
        }

        Flux::toast(variant: 'success', heading: 'Posted', text: "Journal entry #{$entry->id} posted.");
        $this->redirectRoute('vendor.accounting.journal.show', $entry, navigate: true);
    }
};
?>

<div class="p-4 lg:p-6 max-w-5xl">
    <form wire:submit="post">
        <flux:card class="space-y-6">
            <x-accounting.card-header icon="pencil-square" title="Manual journal entry"
                subtitle="For adjustments the app doesn't post automatically. Debits must equal credits." />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input type="date" wire:model="date" label="Date" />
                <flux:input wire:model="reference" label="Reference (optional)" />
                <flux:input wire:model="memo" label="Memo" placeholder="What is this entry for?" />
            </div>

            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-3 py-2">Account</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2 text-right w-36">Debit</th>
                            <th class="px-3 py-2 text-right w-36">Credit</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($lines as $i => $line)
                            <tr wire:key="line-{{ $i }}">
                                <td class="px-3 py-2">
                                    <flux:select wire:model="lines.{{ $i }}.account_id" placeholder="Choose account…" searchable>
                                        @foreach ($this->accounts as $a)
                                            <flux:select.option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </td>
                                <td class="px-3 py-2"><flux:input wire:model="lines.{{ $i }}.description" /></td>
                                <td class="px-3 py-2"><flux:input wire:model.live.debounce.400ms="lines.{{ $i }}.debit" inputmode="decimal" class="text-right font-mono" /></td>
                                <td class="px-3 py-2"><flux:input wire:model.live.debounce.400ms="lines.{{ $i }}.credit" inputmode="decimal" class="text-right font-mono" /></td>
                                <td class="px-1 py-2 text-right">
                                    <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600" wire:click="removeLine({{ $i }})" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-zinc-50 dark:bg-zinc-800/60">
                        <tr>
                            <td class="px-3 py-2" colspan="2">
                                <flux:button size="sm" variant="ghost" icon="plus" wire:click="addLine">Add line</flux:button>
                            </td>
                            <td class="px-3 py-2 text-right"><x-accounting.amount :value="$this->totals['debit']" strong /></td>
                            <td class="px-3 py-2 text-right"><x-accounting.amount :value="$this->totals['credit']" strong /></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @error('lines') <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout> @enderror
            @error('lines.*.debit') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            @error('lines.*.credit') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

            <div class="flex items-center justify-between gap-3 flex-wrap">
                @if ($this->totals['balanced'])
                    <flux:badge color="emerald" icon="check-circle">Balanced</flux:badge>
                @else
                    <flux:badge color="rose" icon="exclamation-triangle">
                        Out of balance by {{ \App\Support\Money\MoneyMath::format(\App\Support\Money\MoneyMath::abs($this->totals['difference'])) }}
                    </flux:badge>
                @endif

                <div class="flex gap-3">
                    <flux:button variant="ghost" href="{{ route('vendor.accounting.journal.index') }}" wire:navigate>Cancel</flux:button>
                    <flux:button type="submit" variant="primary" :disabled="! $this->totals['balanced']" wire:loading.attr="disabled">Post entry</flux:button>
                </div>
            </div>
        </flux:card>
    </form>
</div>
