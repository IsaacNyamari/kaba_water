<?php

use App\Livewire\Concerns\UsesAccounting;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Services\Accounting\Exceptions\AccountingException;
use App\Services\Accounting\LedgerService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Journal Entry')] class extends Component {
    use UsesAccounting;

    public JournalEntry $entry;
    public string $reason = '';

    public function mount(JournalEntry $entry): void
    {
        $this->bootAccounting();
        abort_unless($entry->vendor_id === $this->vendorId(), 404);
        $this->entry = $entry;
    }

    public function reverse(LedgerService $ledger): void
    {
        $this->authorize('accounting.post');
        $this->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $reversal = $ledger->reverse($this->entry, $this->reason);
        } catch (AccountingException|\LogicException $e) {
            Flux::toast(variant: 'danger', heading: 'Cannot reverse', text: $e->getMessage());
            return;
        }

        Flux::modal('reverse-entry')->close();
        Flux::toast(variant: 'success', heading: 'Reversed', text: "Reversal #{$reversal->id} posted.");
        $this->redirectRoute('vendor.accounting.journal.show', $reversal, navigate: true);
    }

    /** Link to the document that produced this entry, when there is a page for it. */
    public function sourceLink(): ?array
    {
        return match ($this->entry->source_type) {
            Invoice::class => ['Invoice ' . ($this->entry->reference ?? '#' . $this->entry->source_id), route('vendor.invoices.show', $this->entry->source_id)],
            Payment::class => ['Payment #' . $this->entry->source_id, route('vendor.payments.index')],
            Expense::class => ['Expense #' . $this->entry->source_id, route('vendor.expenses.edit', $this->entry->source_id)],
            \App\Models\OpeningBalance::class => ['Opening balances', route('vendor.accounting.opening-balances')],
            \App\Models\Asset::class => ['Asset #' . $this->entry->source_id, route('vendor.assets.edit', $this->entry->source_id)],
            default => null,
        };
    }

    public function with(): array
    {
        $this->entry->load(['lines.account', 'lines.client', 'lines.meter', 'creator', 'reverses', 'reversedBy']);

        return ['link' => $this->sourceLink()];
    }
};
?>

<div class="p-4 lg:p-6 max-w-5xl space-y-4">
    <flux:card class="space-y-5">
        <x-accounting.card-header icon="book-open" :title="'Journal entry #' . $entry->id" :subtitle="$entry->memo">
            <x-slot:actions>
                <flux:button href="{{ route('vendor.accounting.journal.index') }}" variant="ghost" icon="arrow-left" wire:navigate>Back</flux:button>
                @can('accounting.post')
                    @unless ($entry->reversed_by_id)
                        <flux:modal.trigger name="reverse-entry">
                            <flux:button variant="danger" icon="arrow-uturn-left">Reverse</flux:button>
                        </flux:modal.trigger>
                    @endunless
                @endcan
            </x-slot:actions>
        </x-accounting.card-header>

        <dl class="grid gap-4 sm:grid-cols-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Date</dt><dd>{{ $entry->date->format('d M Y') }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Reference</dt><dd class="font-mono">{{ $entry->reference ?? '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Posted</dt><dd>{{ $entry->posted_at?->format('d M Y H:i') }} {{ $entry->creator ? 'by ' . $entry->creator->name : '(automatic)' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Source</dt>
                <dd>
                    @if ($link)
                        <flux:link href="{{ $link[1] }}" wire:navigate>{{ $link[0] }}</flux:link>
                    @else
                        Manual entry
                    @endif
                </dd>
            </div>
        </dl>

        @if ($entry->reversedBy)
            <flux:callout variant="warning" icon="arrow-uturn-left">
                Reversed by <flux:link href="{{ route('vendor.accounting.journal.show', $entry->reversedBy) }}" wire:navigate>entry #{{ $entry->reversedBy->id }}</flux:link>.
            </flux:callout>
        @endif
        @if ($entry->reverses)
            <flux:callout icon="information-circle">
                This entry reverses <flux:link href="{{ route('vendor.accounting.journal.show', $entry->reverses) }}" wire:navigate>entry #{{ $entry->reverses->id }}</flux:link>.
            </flux:callout>
        @endif
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Account</flux:table.column>
            <flux:table.column>Description</flux:table.column>
            <flux:table.column>Client / meter</flux:table.column>
            <flux:table.column align="end">Debit</flux:table.column>
            <flux:table.column align="end">Credit</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($entry->lines as $l)
                <flux:table.row :key="$l->id">
                    <flux:table.cell>
                        <span class="font-mono">{{ $l->account->code }}</span> {{ $l->account->name }}
                        <x-accounting.type-badge :type="$l->account->type" />
                    </flux:table.cell>
                    <flux:table.cell class="text-sm">{{ $l->description }}</flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-500">{{ $l->client?->name }} {{ $l->meter ? '· ' . $l->meter->serial : '' }}</flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$l->debit" blank-zero /></flux:table.cell>
                    <flux:table.cell align="end"><x-accounting.amount :value="$l->credit" blank-zero /></flux:table.cell>
                </flux:table.row>
            @endforeach
            <flux:table.row>
                <flux:table.cell colspan="3" class="font-semibold">Total</flux:table.cell>
                <flux:table.cell align="end"><x-accounting.amount :value="$entry->total_debit" strong /></flux:table.cell>
                <flux:table.cell align="end"><x-accounting.amount :value="$entry->total_credit" strong /></flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>

    <flux:modal name="reverse-entry" class="w-full max-w-md">
        <form wire:submit="reverse" class="space-y-6">
            <div>
                <flux:heading size="lg">Reverse entry #{{ $entry->id }}?</flux:heading>
                <flux:subheading>A mirror entry (debits ↔ credits) is posted today. The original stays on record.</flux:subheading>
            </div>
            <flux:input wire:model="reason" label="Reason" placeholder="e.g. Posted to the wrong account" required />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Post reversal</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
