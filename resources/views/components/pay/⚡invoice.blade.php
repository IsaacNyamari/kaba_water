<?php

use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Services\Mpesa\StkPushService;
use App\Support\Money\MoneyMath;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Public pay-by-link page sent with an invoice (signed URL, no login):
 * the customer enters their number and gets an M-Pesa PIN prompt for the
 * exact balance. The page then waits for Safaricom's confirmation.
 */
new #[Layout('layouts.auth'), Title('Pay your bill')] class extends Component {
    #[Locked] public int $invoiceId;
    #[Locked] public ?int $transactionId = null;
    public string $phone = '';
    public ?string $problem = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoiceId = $invoice->id;
        $this->phone = (string) $invoice->client?->phone;
    }

    public function pay(StkPushService $stk): void
    {
        $this->validate(['phone' => ['required', 'string', 'max:20']], [], ['phone' => 'phone number']);
        $this->problem = null;

        $key = 'stk-invoice:'.$this->invoiceId;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->problem = 'Too many payment requests. Try again in a few minutes.';

            return;
        }
        RateLimiter::hit($key, 300);

        try {
            $this->transactionId = $stk->forInvoice(Invoice::withoutGlobalScope('vendor')->findOrFail($this->invoiceId), $this->phone)->id;
        } catch (\Throwable $e) {
            $this->problem = $e instanceof \RuntimeException ? $e->getMessage() : 'M-Pesa is not reachable right now. Please try again.';
            report($e);
        }
    }

    public function again(): void
    {
        $this->transactionId = null;
    }

    public function with(): array
    {
        $invoice = Invoice::withoutGlobalScope('vendor')->with(['client', 'vendor'])->findOrFail($this->invoiceId);

        return [
            'invoice' => $invoice,
            'balance' => MoneyMath::sub((string) $invoice->total, (string) $invoice->amount_paid),
            'transaction' => $this->transactionId ? MpesaTransaction::withoutGlobalScope('vendor')->find($this->transactionId) : null,
        ];
    }
};
?>

<div class="space-y-6" @if ($transaction?->status === 'pending') wire:poll.3s @endif>
    <div class="text-center">
        <p class="text-sm text-zinc-500">{{ $invoice->vendor?->name }}</p>
        <flux:heading size="xl" class="mt-1">Invoice {{ $invoice->number }}</flux:heading>
        <p class="mt-1 text-sm text-zinc-500">{{ $invoice->client?->name }} · Account {{ $invoice->client?->account_no }}</p>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 text-center dark:border-zinc-700 dark:bg-zinc-900">
        <p class="text-xs uppercase tracking-wide text-zinc-500">Balance due</p>
        <p class="mt-1 font-mono text-3xl font-semibold">KES {{ MoneyMath::format($balance) }}</p>
        @if ($invoice->due_at)<p class="mt-1 text-xs text-zinc-500">Due {{ $invoice->due_at->format('d M Y') }}</p>@endif
    </div>

    @if ($transaction && in_array($transaction->status, ['matched', 'credit', 'suspense'], true))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            <flux:icon.check-circle class="mx-auto size-10" />
            <p class="mt-2 font-semibold">Payment received — thank you!</p>
            <p class="mt-1 text-sm">M-Pesa code <span class="font-mono font-semibold">{{ $transaction->receipt }}</span>. A receipt is on its way by SMS.</p>
        </div>
    @elseif ($transaction && $transaction->status === 'failed')
        <div class="space-y-3 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-center text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
            <p class="font-semibold">The payment did not go through.</p>
            <p class="text-sm">{{ $transaction->result_description ?: 'The request was cancelled or timed out.' }}</p>
            <flux:button wire:click="again" size="sm">Try again</flux:button>
        </div>
    @elseif ($transaction)
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-center text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300">
            <flux:icon.device-phone-mobile class="mx-auto size-10 animate-pulse" />
            <p class="mt-2 font-semibold">Check your phone</p>
            <p class="mt-1 text-sm">Enter your M-Pesa PIN to pay KES {{ MoneyMath::format((string) $transaction->amount) }}. This page updates by itself.</p>
        </div>
    @elseif (MoneyMath::isPositive($balance) && $invoice->status->isPayable())
        <form wire:submit="pay" class="space-y-4">
            <flux:input wire:model="phone" label="M-Pesa phone number" placeholder="0712 345 678" inputmode="tel" autocomplete="tel" />
            @if ($problem)<p class="text-sm text-rose-600">{{ $problem }}</p>@endif
            <flux:button type="submit" variant="primary" class="w-full" icon="device-phone-mobile" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="pay">Pay KES {{ MoneyMath::format($balance) }} with M-Pesa</span>
                <span wire:loading wire:target="pay">Sending prompt…</span>
            </flux:button>
            <p class="text-center text-xs text-zinc-500">Or pay via Paybill using account <span class="font-mono font-semibold">{{ $invoice->client?->account_no }}</span>.</p>
        </form>
    @else
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            This invoice has nothing left to pay.
        </div>
    @endif
</div>
