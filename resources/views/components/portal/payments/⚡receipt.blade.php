<?php

use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/* A printable receipt for one of the client's own payments (print or "Save as PDF"). */
new #[Layout('layouts.portal'), Title('Receipt')] class extends Component {
    use InteractsWithClient;

    public Payment $payment;

    public function mount(Payment $payment): void
    {
        abort_unless($payment->client_id === $this->requireClient()->id, 404);
        $this->payment = $payment->load(['invoice', 'client', 'vendor']);
    }
};
?>

<div class="p-4 lg:p-6">
    <div class="mx-auto max-w-lg space-y-4">
        <div class="flex items-center justify-between print:hidden">
            <flux:button variant="ghost" icon="arrow-left" :href="route('portal.payments.index')" wire:navigate>Payments</flux:button>
            <flux:button variant="primary" icon="printer" x-on:click="window.print()">Print / save PDF</flux:button>
        </div>

        <div class="app-card space-y-5 p-6 print:border-0 print:shadow-none">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-lg font-semibold">{{ $payment->vendor?->name }}</p>
                    <p class="text-xs text-zinc-500">{{ $payment->vendor?->email }}{{ $payment->vendor?->phone ? ' · '.$payment->vendor->phone : '' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Receipt</p>
                    <p class="font-mono font-semibold">RCPT-{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</p>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-xs text-zinc-500">Received from</dt><dd class="font-medium">{{ $payment->client?->name }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Account</dt><dd class="font-mono">{{ $payment->client?->account_no }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Date</dt><dd>{{ $payment->paid_at?->format('d M Y H:i') }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Method</dt><dd>{{ $payment->method?->label() }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Reference</dt><dd class="font-mono">{{ $payment->reference ?? '—' }}</dd></div>
                <div><dt class="text-xs text-zinc-500">For invoice</dt><dd class="font-mono">{{ $payment->invoice?->number ?? '—' }}</dd></div>
            </dl>

            <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 dark:bg-emerald-500/10">
                <span class="text-sm font-medium text-emerald-800 dark:text-emerald-300">Amount paid</span>
                <span class="font-mono text-xl font-semibold text-emerald-800 dark:text-emerald-300">{{ $payment->vendor?->currency ?? 'KES' }} {{ number_format((float) $payment->amount, 2) }}</span>
            </div>

            @if ($payment->invoice)
                <p class="text-xs text-zinc-500">Invoice balance after this payment: {{ number_format($payment->invoice->balance(), 2) }}.</p>
            @endif
        </div>
    </div>
</div>
