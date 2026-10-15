<?php

use App\Models\MpesaTransaction;
use App\Models\PrepaidToken;
use App\Services\Prepaid\PrepaidVending;
use App\Services\Sms\SmsService;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Prepaid token sales: every token bought (M-Pesa → arrears deduction →
 * STS token → SMS), with re-issue for failed vends and resend by SMS.
 */
new #[Layout('layouts.vendor', ['title' => 'Prepaid tokens'])] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)] public string $search = '';
    #[Url(history: true)] public string $status = '';

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    /** Try a failed vend again with the money held for it (no second arrears deduction). */
    public function retry(int $id, PrepaidVending $vending): void
    {
        abort_unless(auth()->user()->can('vendor.prepaid.manage'), 403);
        $failed = PrepaidToken::with(['meter', 'transaction'])->findOrFail($id);

        $transaction = $failed->transaction;
        if ($failed->status !== 'failed' || ! $transaction || ! $transaction->hasUnallocated()) {
            Flux::toast(variant: 'warning', text: 'There is no money held for this token to re-issue.');

            return;
        }

        $amount = MoneyMath::compare($transaction->unallocated(), (string) $failed->token_amount) < 0
            ? $transaction->unallocated()
            : (string) $failed->token_amount;

        try {
            $token = $vending->sell($failed->meter, $amount, $transaction, auth()->id(), fromDeposit: true, deductArrears: false);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', heading: 'Could not re-issue', text: $e->getMessage());

            return;
        }

        $transaction->refresh();
        $transaction->update(['status' => $transaction->hasUnallocated() ? 'credit' : 'matched']);

        $token->status === 'issued'
            ? Flux::toast(variant: 'success', heading: 'Token issued', text: $token->formattedToken().' — sent to the customer by SMS.')
            : Flux::toast(variant: 'danger', heading: 'Vending failed again', text: (string) $token->error);
    }

    public function resend(int $id, SmsService $sms): void
    {
        abort_unless(auth()->user()->can('vendor.prepaid.manage'), 403);
        $token = PrepaidToken::with(['meter', 'client', 'transaction'])->where('status', 'issued')->findOrFail($id);

        $log = $sms->send(
            $token->vendor_id,
            $token->transaction?->phone ?: $token->client?->phone,
            "Water token for meter {$token->meter->serial}: {$token->formattedToken()}. Units: {$token->units} m3.",
            'prepaid_token',
            $token->client,
        );

        $log->status === 'failed'
            ? Flux::toast(variant: 'danger', heading: 'SMS not sent', text: (string) $log->error)
            : Flux::toast(variant: 'success', text: 'Token sent again.');
    }

    public function with(): array
    {
        return [
            'tokens' => PrepaidToken::query()
                ->with(['meter', 'client', 'transaction'])
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('token', 'like', '%'.preg_replace('/\s+/', '', $this->search).'%')
                    ->orWhereHas('meter', fn ($q) => $q->where('serial', 'like', "%{$this->search}%"))
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('account_no', 'like', "%{$this->search}%"))
                    ->orWhereHas('transaction', fn ($q) => $q->where('receipt', 'like', "%{$this->search}%"))))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->latest()
                ->paginate(25),
            'summary' => [
                'month_sales' => MoneyMath::str((string) PrepaidToken::where('status', 'issued')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('token_amount')),
                'month_units' => (string) PrepaidToken::where('status', 'issued')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('units'),
                'month_arrears' => MoneyMath::str((string) PrepaidToken::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('arrears_deducted')),
                'failed' => PrepaidToken::where('status', 'failed')->whereHas('transaction', fn ($q) => $q->whereColumn('allocated_amount', '<', 'amount'))->count(),
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-brand flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-sm"><flux:icon.key variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="lg">Prepaid tokens</flux:heading>
                <flux:text class="text-sm">Customers pay with M-Pesa using their meter number as the account; the token is texted to them straight away.</flux:text>
            </div>
        </div>
        @can('vendor.settings.manage')
            <flux:button variant="ghost" icon="cog-6-tooth" :href="route('vendor.settings.index', ['tab' => 'prepaid'])" wire:navigate>Vending settings</flux:button>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card tone-brand p-5"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Token sales this month</span><div class="mt-2 font-mono text-2xl font-semibold">{{ MoneyMath::format($summary['month_sales']) }}</div></div>
        <div class="stat-card tone-sky p-5"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Units sold (m³)</span><div class="mt-2 font-mono text-2xl font-semibold">{{ number_format((float) $summary['month_units'], 3) }}</div></div>
        <div class="stat-card tone-emerald p-5"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Arrears recovered</span><div class="mt-2 font-mono text-2xl font-semibold">{{ MoneyMath::format($summary['month_arrears']) }}</div></div>
        <div class="stat-card tone-{{ $summary['failed'] ? 'rose' : 'violet' }} p-5"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Failed, money held</span><div class="mt-2 font-mono text-2xl font-semibold">{{ $summary['failed'] }}</div></div>
    </div>

    <div class="app-card grid gap-3 p-4 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Meter, customer, token or M-Pesa code" class="sm:col-span-2" />
        <flux:select wire:model.live="status">
            <flux:select.option value="">All tokens</flux:select.option>
            <flux:select.option value="issued">Issued</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
            <flux:select.option value="pending">Pending</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$tokens">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Meter / customer</flux:table.column>
            <flux:table.column align="end">Paid</flux:table.column>
            <flux:table.column align="end">To arrears</flux:table.column>
            <flux:table.column align="end">Units</flux:table.column>
            <flux:table.column>Token</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($tokens as $t)
                <flux:table.row :key="'tok-'.$t->id">
                    <flux:table.cell class="whitespace-nowrap text-sm">
                        {{ $t->created_at->format('d M Y H:i') }}
                        <div class="font-mono text-xs text-zinc-500">{{ $t->transaction?->receipt }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="font-mono text-sm">{{ $t->meter?->serial }}</div>
                        <div class="text-xs text-zinc-500">{{ $t->client?->name ?? '—' }}</div>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format((string) $t->amount_paid) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono {{ MoneyMath::isPositive((string) $t->arrears_deducted) ? 'text-amber-600' : 'text-zinc-400' }}">{{ MoneyMath::format((string) $t->arrears_deducted) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ number_format((float) $t->units, 3) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($t->status === 'issued')
                            <span class="font-mono text-sm tracking-wider">{{ $t->formattedToken() }}</span>
                            @if ($t->provider === 'simulator')<flux:badge size="sm" color="amber" class="ml-1">Test</flux:badge>@endif
                        @else
                            <flux:badge size="sm" :color="$t->status === 'failed' ? 'red' : 'zinc'">{{ ucfirst($t->status) }}</flux:badge>
                            @if ($t->error)<div class="mt-1 max-w-[16rem] text-xs text-rose-600">{{ $t->error }}</div>@endif
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('vendor.prepaid.manage')
                            @if ($t->status === 'issued')
                                <flux:button size="sm" variant="ghost" icon="chat-bubble-left-ellipsis" wire:click="resend({{ $t->id }})">Resend</flux:button>
                            @elseif ($t->status === 'failed' && $t->transaction?->hasUnallocated())
                                <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="retry({{ $t->id }})" wire:confirm="Ask the vending system for this token again?">Re-issue</flux:button>
                            @endif
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="7" class="py-10 text-center text-sm text-zinc-500">No tokens sold yet. Mark meters as prepaid and connect M-Pesa to start selling tokens.</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
