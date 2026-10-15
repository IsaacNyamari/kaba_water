<?php

use App\Models\Meter;
use App\Models\MpesaTransaction;
use App\Services\Mpesa\StkPushService;
use App\Support\Money\MoneyMath;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Public "buy water token" page for a prepaid meter (signed URL, no login).
 * M-Pesa PIN prompt → token shown here and sent by SMS.
 */
new #[Layout('layouts.auth'), Title('Buy water token')] class extends Component {
    #[Locked] public int $meterId;
    #[Locked] public ?int $transactionId = null;
    public string $phone = '';
    public string $amount = '';
    public ?string $problem = null;

    public function mount(Meter $meter): void
    {
        abort_unless($meter->isPrepaid(), 404);
        $this->meterId = $meter->id;
        $this->phone = (string) $meter->client?->phone;
    }

    public function buy(StkPushService $stk): void
    {
        $this->validate([
            'phone' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'integer', 'min:10', 'max:150000'],
        ], [], ['phone' => 'phone number']);
        $this->problem = null;

        $key = 'stk-meter:'.$this->meterId;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->problem = 'Too many requests. Try again in a few minutes.';

            return;
        }
        RateLimiter::hit($key, 300);

        try {
            $this->transactionId = $stk->forMeterToken(Meter::withoutGlobalScope('vendor')->findOrFail($this->meterId), $this->phone, (string) (int) $this->amount)->id;
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
        $transaction = $this->transactionId ? MpesaTransaction::withoutGlobalScope('vendor')->with('token')->find($this->transactionId) : null;

        return [
            'meter' => Meter::withoutGlobalScope('vendor')->with(['client', 'vendor'])->findOrFail($this->meterId),
            'transaction' => $transaction,
            'token' => $transaction?->token,
        ];
    }
};
?>

<div class="space-y-6" @if ($transaction && ($transaction->status === 'pending' || ($transaction->status !== 'failed' && ! $token))) wire:poll.3s @endif>
    <div class="text-center">
        <p class="text-sm text-zinc-500">{{ $meter->vendor?->name }}</p>
        <flux:heading size="xl" class="mt-1">Buy water token</flux:heading>
        <p class="mt-1 text-sm text-zinc-500">Meter <span class="font-mono">{{ $meter->serial }}</span>{{ $meter->client ? ' · '.$meter->client->name : '' }}</p>
    </div>

    @if ($token && $token->status === 'issued')
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            <p class="text-xs uppercase tracking-wide">Your token</p>
            <p class="mt-2 font-mono text-2xl font-semibold tracking-widest">{{ $token->formattedToken() }}</p>
            <p class="mt-2 text-sm">{{ number_format((float) $token->units, 3) }} m³ · KES {{ MoneyMath::format((string) $token->token_amount) }}
                @if (MoneyMath::isPositive((string) $token->arrears_deducted)) (KES {{ MoneyMath::format((string) $token->arrears_deducted) }} went to arrears) @endif
            </p>
            <p class="mt-1 text-xs">Type it on the meter keypad. It has also been sent by SMS.</p>
        </div>
    @elseif ($token && $token->status === 'failed')
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-center text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            Payment received ({{ $transaction->receipt }}), but the token could not be issued right now. Your money is safe — the water office has been told and will send your token shortly.
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
            <p class="mt-2 font-semibold">{{ $transaction->status === 'pending' ? 'Check your phone' : 'Getting your token…' }}</p>
            <p class="mt-1 text-sm">{{ $transaction->status === 'pending' ? 'Enter your M-Pesa PIN to pay KES '.MoneyMath::format((string) $transaction->amount).'.' : 'Payment received. Your token will appear here in a moment.' }}</p>
        </div>
    @else
        <form wire:submit="buy" class="space-y-4">
            <div class="grid grid-cols-4 gap-2">
                @foreach ([100, 200, 500, 1000] as $preset)
                    <flux:button size="sm" :variant="$amount === (string) $preset ? 'primary' : 'filled'" wire:click="$set('amount', '{{ $preset }}')">{{ number_format($preset) }}</flux:button>
                @endforeach
            </div>
            <flux:input type="number" min="10" step="1" wire:model.live="amount" label="Amount (KES)" />
            <flux:input wire:model="phone" label="M-Pesa phone number" placeholder="0712 345 678" inputmode="tel" autocomplete="tel" />
            @if ($problem)<p class="text-sm text-rose-600">{{ $problem }}</p>@endif
            <flux:button type="submit" variant="primary" class="w-full" icon="key" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="buy">Buy token with M-Pesa</span>
                <span wire:loading wire:target="buy">Sending prompt…</span>
            </flux:button>
            <p class="text-center text-xs text-zinc-500">Or pay via Paybill using account <span class="font-mono font-semibold">{{ $meter->serial }}</span>.</p>
        </form>
    @endif
</div>
