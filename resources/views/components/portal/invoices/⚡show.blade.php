<?php

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.portal')] class extends Component {
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice->load(['items', 'client', 'reading.meter', 'vendor', 'payments']);
    }

    public function title(): string
    {
        return 'Invoice ' . $this->invoice->number;
    }

    public function downloadPdf(InvoicePdfService $pdf)
    {
        $this->authorize('downloadPdf', $this->invoice);

        return response()->file($pdf->path($this->invoice), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->invoice->number . '.pdf"',
        ]);
    }

    public function rendering(): void
    {
        $this->invoice->loadMissing(['items', 'client', 'reading.meter', 'vendor', 'payments']);
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">

    {{-- Back + Header --}}
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('portal.invoices.index') }}" />
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $invoice->number }}
                </h1>
                <flux:badge :color="$invoice->status->color()" size="sm">
                    {{ $invoice->status->label() }}
                </flux:badge>
                @if ($invoice->isOverdue())
                    <flux:badge color="red" size="sm" icon="exclamation-triangle">
                        Overdue {{ $invoice->due_at->diffForHumans() }}
                    </flux:badge>
                @endif
            </div>
            <p class="text-sm text-zinc-500 mt-1">
                Issued {{ $invoice->issued_at->format('d M Y') }} ·
                Due {{ $invoice->due_at->format('d M Y') }}
            </p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Left: full invoice detail --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Parties --}}
            <div class="app-card p-5">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold">From</div>
                        <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $invoice->vendor->name }}
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $invoice->vendor->email }}
                            @if ($invoice->vendor->phone)
                                <br>{{ $invoice->vendor->phone }}
                            @endif
                        </div>
                    </div>

                    @if ($invoice->reading?->meter)
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold">Meter</div>
                            <div class="mt-1 font-mono text-sm">{{ $invoice->reading->meter->serial }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1 space-y-0.5">
                                <div>Prev: <span
                                        class="font-mono">{{ number_format($invoice->reading->previous_reading ?? 0) }}</span>
                                </div>
                                <div>Current: <span
                                        class="font-mono">{{ number_format($invoice->reading->reading) }}</span></div>
                                <div class="font-semibold text-brand-600">
                                    Used {{ number_format((float) $invoice->reading->consumption) }} m³
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Items --}}
            <div
                class="overflow-hidden app-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="text-left text-xs uppercase tracking-wide text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3 text-right">Qty (m³)</th>
                            <th class="px-5 py-3 text-right">Unit price</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr wire:key="item-{{ $item->id }}"
                                class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                <td class="px-5 py-3 text-zinc-900 dark:text-zinc-100">{{ $item->description }}</td>
                                <td class="px-5 py-3 text-right font-mono">
                                    {{ number_format((float) $item->quantity, 3) }}</td>
                                <td class="px-5 py-3 text-right font-mono">
                                    {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold">
                                    {{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoice->notes)
                <div class="app-card p-5">
                    <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold">Notes</div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $invoice->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Right: totals + actions --}}
        <div class="space-y-4">
            <div class="app-card p-5">
                <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold">Summary</div>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Subtotal</span>
                        <span class="font-mono">{{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    @if ((float) $invoice->tax > 0)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Tax</span>
                            <span class="font-mono">{{ number_format($invoice->tax, 2) }}</span>
                        </div>
                    @endif
                    <div
                        class="flex justify-between text-lg font-bold text-brand-600 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                        <span>Total</span>
                        <span class="font-mono">{{ number_format($invoice->total, 2) }}</span>
                    </div>
                    @if ((float) $invoice->amount_paid > 0)
                        <div class="flex justify-between text-accent-600">
                            <span>Paid</span>
                            <span class="font-mono">{{ number_format($invoice->amount_paid, 2) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold text-zinc-900 dark:text-zinc-100">
                            <span>Balance</span>
                            <span class="font-mono">{{ number_format($invoice->balance(), 2) }}</span>
                        </div>
                    @endif
                    <div class="text-xs text-zinc-500 pt-1 text-right">
                        {{ $invoice->vendor->currency }}
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <flux:button href="{{ route('portal.invoices.pdf', $invoice) }}" target="_blank" variant="filled" icon="arrow-down-tray" class="w-full">
                        Download PDF
                    </flux:button>

                    @if ($invoice->status->isPayable())
                        <flux:button variant="primary" icon="credit-card" class="w-full"
                            href="{{ route('portal.payments.index') }}">
                            How to pay
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($invoice->payments->count())
                <div class="app-card p-5">
                    <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold">Payments received</div>
                    <flux:table class="p-2">
                        <flux:table.columns>
                            <flux:table.column>Date</flux:table.column>
                            <flux:table.column>Method</flux:table.column>
                            <flux:table.column align="end">Amount</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($invoice->payments as $payment)
                                <flux:table.row :key="$payment->id">
                                    <flux:table.cell class="whitespace-nowrap">
                                        {{ $payment->paid_at->format('d M Y') }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ $payment->method->label() }}
                                        @if ($payment->reference)
                                            <div class="text-xs text-zinc-500 font-mono">{{ $payment->reference }}
                                            </div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" variant="strong" class="font-mono text-accent-600">
                                        {{ number_format($payment->amount, 2) }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    </div>
</div>
