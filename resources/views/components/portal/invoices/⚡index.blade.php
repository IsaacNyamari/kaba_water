<?php

use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Invoice;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.portal'), Title('Invoices')] class extends Component {
    use InteractsWithClient, WithPagination;

    #[Url(history: true)]
    public string $status = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    public function with(): array
    {
        return [
            'invoices' => Invoice::query()->where('client_id', $this->client->id)->where('status', '!=', 'draft')->when($this->status, fn($q) => $q->where('status', $this->status))->latest('issued_at')->paginate(15),

            'summary' => [
                'unpaid' => Invoice::where('client_id', $this->client->id)
                    ->whereIn('status', ['sent', 'overdue'])
                    ->sum(\DB::raw('total - amount_paid')),
                'paid' => Invoice::where('client_id', $this->client->id)->where('status', 'paid')->sum('total'),
                'count' => Invoice::where('client_id', $this->client->id)->where('status', '!=', 'draft')->count(),
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">

    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Invoices</h1>
        <p class="text-sm text-zinc-500 mt-1">All invoices issued to your account.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="app-card p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Total invoices</div>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                {{ number_format($summary['count']) }}
            </div>
        </div>
        <div class="app-card p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Unpaid</div>
            <div class="text-2xl font-semibold text-rose-600 mt-1">
                {{ number_format($summary['unpaid'], 2) }}
            </div>
        </div>
        <div class="app-card p-5">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Paid</div>
            <div class="text-2xl font-semibold text-accent-600 mt-1">
                {{ number_format($summary['paid'], 2) }}
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between gap-3">
        <flux:select wire:model.live="status" class="w-full sm:w-48">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (\App\Enums\InvoiceStatus::cases() as $s)
                <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-hidden app-card">
        <table class="w-full text-sm">
            <thead>
                <tr
                    class="text-left text-xs uppercase tracking-wide text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                    <th class="px-5 py-3">Number</th>
                    <th class="px-5 py-3">Issued</th>
                    <th class="px-5 py-3">Due</th>
                    <th class="px-5 py-3 text-right">Total</th>
                    <th class="px-5 py-3 text-right">Balance</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr wire:key="inv-{{ $invoice->id }}"
                        class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                        <td class="px-5 py-3 font-mono text-xs">
                            <a href="{{ route('portal.invoices.show', $invoice) }}"
                                class="text-brand-600 hover:underline">{{ $invoice->number }}</a>
                        </td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                            {{ $invoice->issued_at->format('d M Y') }}
                        </td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                            {{ $invoice->due_at->format('d M Y') }}
                            @if ($invoice->isOverdue())
                                <div class="text-xs text-rose-600">{{ $invoice->due_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-mono">
                            {{ number_format($invoice->total, 2) }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono">
                            @if ($invoice->balance() > 0)
                                <span class="text-rose-600 font-semibold">
                                    {{ number_format($invoice->balance(), 2) }}
                                </span>
                            @else
                                <span class="text-accent-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <flux:badge :color="$invoice->status->color()" size="sm">
                                {{ $invoice->status->label() }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="inline-flex gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye"
                                    href="{{ route('portal.invoices.show', $invoice) }}" />
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                                    href="{{ route('portal.invoices.pdf', $invoice) }}" target="_blank" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-zinc-500">
                            <flux:icon name="document-text" class="w-10 h-10 mx-auto mb-3 text-zinc-300" />
                            <div class="font-medium">No invoices yet</div>
                            <div class="text-sm">Invoices appear here once your vendor issues them.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($invoices->hasPages())
            <div class="px-5 py-3 border-t border-zinc-200 dark:border-zinc-700">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
