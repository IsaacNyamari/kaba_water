<?php

use App\Livewire\Concerns\RefreshesOnActivity;

use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Invoice;
use App\Models\Payment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.portal'), Title('Payments')] class extends Component {
    use InteractsWithClient, WithPagination, RefreshesOnActivity;

    #[Url(history: true)]
    public string $method = '';

    public function updatingMethod(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    #[Computed]
    public function outstanding()
    {
        return Invoice::where('client_id', $this->client->id)
            ->whereIn('status', ['sent', 'overdue'])
            ->orderBy('due_at')
            ->get();
    }

    public function with(): array
    {
        return [
            'payments' => Payment::query()->where('client_id', $this->client->id)->with('invoice')->when($this->method, fn($q) => $q->where('method', $this->method))->latest('paid_at')->paginate(20),

            'summary' => [
                'this_month' => Payment::where('client_id', $this->client->id)
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('amount'),
                'total' => Payment::where('client_id', $this->client->id)->sum('amount'),
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">

    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Payments</h1>
        <p class="text-sm text-zinc-500 mt-1">Payment history and outstanding invoices.</p>
    </div>

    {{-- Outstanding notice --}}
    @if ($this->outstanding->count())
        <div class="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-5">
            <div class="flex items-start gap-3">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                <div class="flex-1">
                    <div class="font-medium text-amber-900 dark:text-amber-200">
                        You have {{ $this->outstanding->count() }} outstanding
                        invoice{{ $this->outstanding->count() === 1 ? '' : 's' }}
                    </div>
                    <div class="text-sm text-amber-800 dark:text-amber-300 mt-1">
                        Contact {{ $this->client->vendor->name }} to arrange payment.
                        @if ($this->client->vendor->phone)
                            Call <strong>{{ $this->client->vendor->phone }}</strong>
                        @endif
                        @if ($this->client->vendor->email)
                            or email <strong>{{ $this->client->vendor->email }}</strong>
                        @endif
                    </div>
                </div>
                <flux:button size="sm" variant="filled" href="{{ route('portal.invoices.index') }}">
                    View invoices
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Summary --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="stat-card tone-emerald p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Paid this month</div>
            <div class="text-2xl font-semibold text-accent-600 mt-1">
                {{ number_format($summary['this_month'], 2) }}
            </div>
        </div>
        <div class="stat-card tone-sky p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Total paid</div>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                {{ number_format($summary['total'], 2) }}
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="flex items-center gap-3">
        <flux:select wire:model.live="method" class="w-full sm:w-48">
            <flux:select.option value="">All methods</flux:select.option>
            @foreach (\App\Enums\PaymentMethod::cases() as $m)
                <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden app-card">
        <table class="w-full text-sm">
            <thead>
                <tr
                    class="text-left text-xs uppercase tracking-wide text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Invoice</th>
                    <th class="px-5 py-3">Method</th>
                    <th class="px-5 py-3">Reference</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr wire:key="pay-{{ $payment->id }}"
                        class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                        <td class="px-5 py-3 whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                            {{ $payment->paid_at->format('d M Y') }}
                            <div class="text-xs text-zinc-500">{{ $payment->paid_at->format('H:i') }}</div>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs">
                            @if ($payment->invoice)
                                <a href="{{ route('portal.invoices.show', $payment->invoice) }}"
                                    class="text-brand-600 hover:underline">{{ $payment->invoice->number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-400">{{ $payment->method->label() }}</td>
                        <td class="px-5 py-3 font-mono text-xs text-zinc-500">{{ $payment->reference ?? '—' }}</td>
                        <td class="px-5 py-3 text-right font-mono font-semibold text-accent-600">
                            {{ number_format($payment->amount, 2) }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <flux:button size="xs" variant="ghost" icon="receipt-percent" :href="route('portal.payments.receipt', $payment)" wire:navigate>Receipt</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-zinc-500">
                            <flux:icon name="banknotes" class="w-10 h-10 mx-auto mb-3 text-zinc-300" />
                            <div class="font-medium">No payments yet</div>
                            <div class="text-sm">Your payment history will appear here.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($payments->hasPages())
            <div class="px-5 py-3 border-t border-zinc-200 dark:border-zinc-700">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
