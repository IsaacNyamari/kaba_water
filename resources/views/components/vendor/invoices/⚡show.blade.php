<?php

use App\Actions\MarkInvoicePaid;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Invoice')] class extends Component {
    public Invoice $invoice;

    // "Mark as paid" form state
    public bool $showMarkPaid = false;
    public string $payment_method = 'other';
    public string $payment_reference = '';

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice->load(['items', 'client', 'reading.meter', 'payments']);
    }

    public function send(InvoicePdfService $pdf): void
    {
        $this->authorize('send', $this->invoice);

        $this->invoice->update([
            'status' => InvoiceStatus::Sent,
            'issued_at' => $this->invoice->issued_at ?? now(),
        ]);

        $pdf->regenerate($this->invoice);

        Flux::toast(variant: 'success', heading: 'Sent',text:"Invoice marked as sent");
        $this->invoice->refresh();
    }

    public function openMarkPaid(): void
    {
        $this->authorize('update', $this->invoice);
        $this->showMarkPaid = true;
        $this->payment_method = PaymentMethod::Cash->value;
        $this->payment_reference = '';
    }

    public function markPaid(MarkInvoicePaid $action): void
    {
        $this->authorize('update', $this->invoice);

        try {
            $payment = $action->handle($this->invoice, PaymentMethod::from($this->payment_method), $this->payment_reference ?: null);

            Flux::toast(variant: 'success', text: 'Invoice marked as paid', text: 'Payment of ' . number_format($payment->amount, 2) . ' recorded.');

            $this->showMarkPaid = false;
            $this->invoice->refresh();
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', heading: 'Could not mark as paid', text: $e->getMessage());
        }
    }

    public function void(InvoicePdfService $pdf): void
    {
        $this->authorize('update', $this->invoice);

        $this->invoice->update(['status' => InvoiceStatus::Void]);
        $pdf->regenerate($this->invoice);

        Flux::toast(variant: 'warning', text: 'Invoice voided');
        $this->invoice->refresh();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->invoice);

        $number = $this->invoice->number;
        $this->invoice->delete();

        session()->flash('status', "Invoice {$number} deleted.");
        $this->redirectRoute('vendor.invoices.index', navigate: true);
    }

    public function rendering(): void
    {
        $this->invoice->loadMissing(['items', 'client', 'reading.meter', 'payments']);
    }
};
?>

<div class="space-y-6" @if ($invoice->number)  @endif>

    {{-- Header bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $invoice->number }}</flux:heading>
                <flux:badge :color="$invoice->status->color()" size="sm">
                    {{ $invoice->status->label() }}
                </flux:badge>
                @if ($invoice->isOverdue())
                    <flux:badge color="red" size="sm" icon="exclamation-triangle">
                        Overdue {{ $invoice->due_at->diffForHumans() }}
                    </flux:badge>
                @endif
            </div>
            <flux:text class="mt-1 text-sm text-slate-500">
                Issued {{ $invoice->issued_at->format('d M Y') }} ·
                Due {{ $invoice->due_at->format('d M Y') }}
            </flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button href="{{ route('vendor.invoices.pdf', $invoice) }}" target="_blank" variant="filled"
                icon="arrow-down-tray">
                PDF
            </flux:button>

            @if ($invoice->status->value === 'draft')
                <flux:button wire:click="send" variant="primary" icon="paper-airplane">
                    Mark as sent
                </flux:button>
            @endif

            @if ($invoice->status->isPayable())
                <flux:button wire:click="openMarkPaid" variant="primary" icon="check-circle">
                    Mark as paid
                </flux:button>
            @endif

            @if (!in_array($invoice->status->value, ['paid', 'void']))
                <flux:button wire:click="void" variant="ghost" icon="x-circle" wire:confirm="Void this invoice?">
                    Void
                </flux:button>
            @endif

            <flux:button wire:click="delete" variant="ghost" icon="trash" class="!text-rose-600"
                wire:confirm="Delete this invoice permanently?">
                Delete
            </flux:button>
        </div>
    </div>

    {{-- Parties + Meta --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2">
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Billed to</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $invoice->client->name }}</div>
                    <div class="text-sm text-slate-600">
                        <div class="font-mono text-xs">{{ $invoice->client->account_no }}</div>
                        @if ($invoice->client->phone)
                            <div>{{ $invoice->client->phone }}</div>
                        @endif
                        @if ($invoice->client->email)
                            <div>{{ $invoice->client->email }}</div>
                        @endif
                        @if ($invoice->client->address)
                            <div class="mt-1 text-xs">{{ $invoice->client->address }}</div>
                        @endif
                    </div>
                </div>

                @if ($invoice->reading?->meter)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Meter</div>
                        <div class="mt-1 font-mono text-sm">{{ $invoice->reading->meter->serial }}</div>
                        <div class="text-sm text-slate-600 mt-1 space-y-0.5">
                            <div>Prev: <span
                                    class="font-mono">{{ number_format($invoice->reading->previous_reading ?? 0) }}</span>
                            </div>
                            <div>Current: <span
                                    class="font-mono">{{ number_format($invoice->reading->reading) }}</span></div>
                            <div class="font-semibold text-brand-700">
                                Consumption: {{ number_format((float) $invoice->reading->consumption) }} m³
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Amount</div>
            <div class="mt-2 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Subtotal</span>
                    <span class="font-mono">{{ number_format($invoice->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Tax</span>
                    <span class="font-mono">{{ number_format($invoice->tax, 2) }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold text-brand-700 pt-2 border-t border-slate-200">
                    <span>Total</span>
                    <span class="font-mono">{{ number_format($invoice->total, 2) }}</span>
                </div>
                @if ((float) $invoice->amount_paid > 0)
                    <div class="flex justify-between text-accent-600">
                        <span>Paid</span>
                        <span class="font-mono">{{ number_format($invoice->amount_paid, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-semibold">
                        <span>Balance</span>
                        <span class="font-mono">{{ number_format($invoice->balance(), 2) }}</span>
                    </div>
                @endif
                <div class="text-xs text-slate-500 pt-1">{{ $invoice->vendor->currency }}</div>
            </div>
        </div>
    </div>

    {{-- Items --}}
    <flux:card>
        <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="sm">Line items</flux:heading>
        </div>

        <flux:table class="p-2">
            <flux:table.columns>
                <flux:table.column>Description</flux:table.column>
                <flux:table.column align="end">Qty (m³)</flux:table.column>
                <flux:table.column align="end">Unit price</flux:table.column>
                <flux:table.column align="end">Total</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($invoice->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>{{ $item->description }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">
                            {{ number_format((float) $item->quantity, 3) }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">
                            {{ number_format((float) $item->unit_price, 2) }}
                        </flux:table.cell>
                        <flux:table.cell align="end" variant="strong" class="font-mono">
                            {{ number_format($item->total, 2) }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($invoice->notes)
        <div class="card p-5">
            <flux:heading size="sm">Notes</flux:heading>
            <flux:text class="mt-2 text-sm text-slate-600">{{ $invoice->notes }}</flux:text>
        </div>
    @endif

    <flux:modal wire:model.self="showMarkPaid" class="w-full max-w-md">
        <form wire:submit="markPaid" class="space-y-5">
            <div>
                <flux:heading size="lg">Mark {{ $invoice->number }} as paid</flux:heading>
                <flux:subheading>
                    This records a payment of
                    <strong>{{ number_format($invoice->balance(), 2) }} {{ $invoice->vendor->currency }}</strong>
                    against the invoice.
                </flux:subheading>
            </div>

            <flux:select wire:model="payment_method" label="Payment method">
                @foreach (\App\Enums\PaymentMethod::cases() as $m)
                    <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="payment_reference" label="Reference (optional)"
                placeholder="M-Pesa code, cheque no, or note" />

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showMarkPaid', false)">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check-circle" wire:loading.attr="disabled"
                    wire:target="markPaid">
                    <span wire:loading.remove wire:target="markPaid">Confirm payment</span>
                    <span wire:loading wire:target="markPaid">Recording…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
