<?php

use App\Models\Client;
use App\Models\Meter;
use App\Models\MpesaTransaction;
use App\Models\SmsMessage;
use App\Services\Mpesa\MpesaReconciler;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * M-Pesa payments: everything that came into the Paybill / Till or was
 * requested by STK push, how each was applied, the suspense queue for the
 * accountant, and the SMS log.
 */
new #[Layout('layouts.vendor', ['title' => 'M-Pesa payments'])] class extends Component {
    use WithPagination;

    #[Url(history: true)] public string $section = 'transactions';
    #[Url(as: 'q', history: true)] public string $search = '';
    #[Url(history: true)] public string $filter = '';
    #[Url(history: true)] public ?string $from = null;
    #[Url(history: true)] public ?string $to = null;

    public ?int $reconcilingId = null;
    public string $target = '';

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'filter', 'from', 'to', 'section'], true)) {
            $this->resetPage();
        }
    }

    public function startReconcile(int $id): void
    {
        abort_unless(auth()->user()->can('vendor.mpesa.reconcile'), 403);
        $t = MpesaTransaction::findOrFail($id);
        $this->reconcilingId = $t->id;
        $this->target = $t->meter_id ? "meter:{$t->meter_id}" : ($t->client_id ? "client:{$t->client_id}" : '');
        $this->resetValidation();
        Flux::modal('reconcile')->show();
    }

    public function reconcile(MpesaReconciler $reconciler): void
    {
        abort_unless(auth()->user()->can('vendor.mpesa.reconcile'), 403);
        $this->validate(['target' => ['required', 'regex:/^(client|meter):\d+$/']], ['target.required' => 'Choose the customer or prepaid meter this payment belongs to.']);

        $t = MpesaTransaction::findOrFail($this->reconcilingId);

        if (! $t->hasUnallocated() || ! in_array($t->status, ['suspense', 'credit'], true)) {
            Flux::toast(variant: 'warning', text: 'This payment has already been fully applied.');
            Flux::modal('reconcile')->close();

            return;
        }

        [$kind, $id] = explode(':', $this->target);
        $client = $kind === 'client' ? Client::findOrFail($id) : null;
        $meter = $kind === 'meter' ? Meter::findOrFail($id) : null;

        try {
            $reconciler->assign($t, $client, $meter, auth()->id());
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', heading: 'Could not apply payment', text: $e->getMessage());

            return;
        }

        $t->refresh();
        Flux::modal('reconcile')->close();
        Flux::toast(
            variant: 'success',
            heading: 'Payment applied',
            text: $t->status === 'matched'
                ? "{$t->receipt} is fully applied."
                : "{$t->receipt}: KES ".MoneyMath::format($t->unallocated()).' is held as credit for the customer\'s next bill.',
        );
    }

    public function export(): StreamedResponse
    {
        $rows = $this->transactionsQuery()->with('client')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Receipt', 'Channel', 'Phone', 'Payer', 'Account reference', 'Customer', 'Account no', 'Amount', 'Applied', 'Unapplied', 'Status']);
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->transacted_at?->format('Y-m-d H:i'), $t->receipt, strtoupper($t->channel), $t->phone, $t->payer_name,
                    $t->account_reference, $t->client?->name, $t->client?->account_no, (string) $t->amount,
                    (string) $t->allocated_amount, $t->unallocated(), MpesaTransaction::STATUSES[$t->status] ?? $t->status,
                ]);
            }
            fclose($out);
        }, 'mpesa-transactions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function transactionsQuery()
    {
        return MpesaTransaction::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('receipt', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('account_reference', 'like', "%{$this->search}%")
                ->orWhere('payer_name', 'like', "%{$this->search}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('account_no', 'like', "%{$this->search}%"))))
            ->when($this->filter === 'attention', fn ($q) => $q->needsAttention())
            ->when(array_key_exists($this->filter, MpesaTransaction::STATUSES), fn ($q) => $q->where('status', $this->filter))
            ->when($this->from, fn ($q) => $q->whereDate('transacted_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('transacted_at', '<=', $this->to))
            ->latest('transacted_at')
            ->latest('id');
    }

    public function with(): array
    {
        $received = fn () => MpesaTransaction::whereIn('status', ['matched', 'credit', 'suspense']);

        return [
            'transactions' => $this->section === 'transactions' ? $this->transactionsQuery()->with(['client', 'meter', 'invoice'])->paginate(25) : null,
            'messages' => $this->section === 'sms' ? SmsMessage::query()->with('client')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('to', 'like', "%{$this->search}%")->orWhere('message', 'like', "%{$this->search}%")))
                ->latest()->paginate(25) : null,
            'summary' => [
                'today' => MoneyMath::str((string) $received()->whereDate('transacted_at', today())->sum('amount')),
                'month' => MoneyMath::str((string) $received()->whereBetween('transacted_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')),
                'suspense_count' => MpesaTransaction::where('status', 'suspense')->count(),
                'suspense' => MoneyMath::str((string) MpesaTransaction::where('status', 'suspense')->sum(\DB::raw('amount - allocated_amount'))),
                'credit' => MoneyMath::str((string) MpesaTransaction::where('status', 'credit')->sum(\DB::raw('amount - allocated_amount'))),
            ],
            'reconciling' => $this->reconcilingId ? MpesaTransaction::find($this->reconcilingId) : null,
            'clients' => $this->reconcilingId ? Client::orderBy('name')->get(['id', 'name', 'account_no', 'house_number', 'phone']) : collect(),
            'prepaidMeters' => $this->reconcilingId ? Meter::where('billing_mode', 'prepaid')->with('client:id,name')->orderBy('serial')->get(['id', 'serial', 'client_id']) : collect(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-emerald flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm"><flux:icon.device-phone-mobile variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="lg">M-Pesa payments</flux:heading>
                <flux:text class="text-sm">Paybill / Till payments and STK requests, reconciled to customers automatically. Anything that could not be matched waits in suspense.</flux:text>
            </div>
        </div>
        <div class="flex gap-2">
            <flux:button icon="arrow-down-tray" wire:click="export">Export CSV</flux:button>
            @can('vendor.settings.manage')
                <flux:button variant="ghost" icon="cog-6-tooth" :href="route('vendor.settings.index', ['tab' => 'mpesa'])" wire:navigate>Set up</flux:button>
            @endcan
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Received today', $summary['today'], 'emerald', null],
            ['This month', $summary['month'], 'brand', null],
            ['In suspense', $summary['suspense'], 'amber', $summary['suspense_count'].' payment(s) to reconcile'],
            ['Customer credit held', $summary['credit'], 'sky', 'Applied to the next bills automatically'],
        ] as [$label, $value, $tone, $hint])
            <div wire:key="mp-kpi-{{ $loop->index }}" class="stat-card tone-{{ $tone }} p-5">
                <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $label }}</span>
                <div class="mt-2 font-mono text-2xl font-semibold">{{ MoneyMath::format($value) }}</div>
                @if ($hint)<p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p>@endif
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach (['transactions' => ['Transactions', 'banknotes'], 'sms' => ['SMS log', 'chat-bubble-left-right']] as $key => [$label, $icon])
            <flux:button size="sm" :variant="$section === $key ? 'primary' : 'ghost'" :icon="$icon" wire:click="$set('section', '{{ $key }}')">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($section === 'sms')
        <div class="app-card p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search number or message" class="sm:max-w-sm" />
        </div>
        <flux:table :paginate="$messages">
            <flux:table.columns>
                <flux:table.column>Sent</flux:table.column>
                <flux:table.column>To</flux:table.column>
                <flux:table.column>Message</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($messages as $m)
                    <flux:table.row :key="'sms-'.$m->id">
                        <flux:table.cell class="whitespace-nowrap text-sm">{{ $m->created_at->format('d M Y H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-mono text-xs">{{ $m->to }}</div>
                            <div class="text-xs text-zinc-500">{{ $m->client?->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-md whitespace-normal text-sm">{{ $m->message }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="['sent' => 'emerald', 'failed' => 'red', 'logged' => 'zinc'][$m->status] ?? 'zinc'">{{ $m->status === 'logged' ? 'Logged only' : ucfirst($m->status) }}</flux:badge>
                            @if ($m->error)<div class="mt-1 max-w-[14rem] text-xs text-rose-600">{{ $m->error }}</div>@endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4" class="py-10 text-center text-sm text-zinc-500">No SMS yet.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @else
        <div class="app-card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Receipt, phone, account, name" />
            <flux:select wire:model.live="filter">
                <flux:select.option value="">All payments</flux:select.option>
                <flux:select.option value="attention">Needs attention (suspense + credit)</flux:select.option>
                @foreach (MpesaTransaction::STATUSES as $k => $l)
                    <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="from" />
            <flux:input type="date" wire:model.live="to" />
        </div>

        <flux:table :paginate="$transactions">
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Receipt</flux:table.column>
                <flux:table.column>Paid by</flux:table.column>
                <flux:table.column>Account ref.</flux:table.column>
                <flux:table.column>Applied to</flux:table.column>
                <flux:table.column align="end">Amount</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transactions as $t)
                    <flux:table.row :key="'mp-'.$t->id">
                        <flux:table.cell class="whitespace-nowrap text-sm">
                            {{ $t->transacted_at?->format('d M Y') }}
                            <div class="text-xs text-zinc-500">{{ $t->transacted_at?->format('H:i') }} · {{ strtoupper($t->channel) }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $t->receipt ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="text-sm">{{ $t->payer_name ?: '—' }}</div>
                            <div class="font-mono text-xs text-zinc-500">{{ $t->phone }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $t->account_reference ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-sm">
                            @if ($t->client)
                                <div class="font-medium">{{ $t->client->name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $t->client->account_no }}
                                    @if ($t->meter) · token for {{ $t->meter->serial }} @elseif ($t->invoice) · {{ $t->invoice->number }} @endif
                                </div>
                            @else
                                <span class="text-zinc-400">Not matched</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">
                            {{ MoneyMath::format((string) $t->amount) }}
                            @if ($t->hasUnallocated() && in_array($t->status, ['credit', 'suspense'], true))
                                <div class="text-xs text-amber-600">{{ MoneyMath::format($t->unallocated()) }} unapplied</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$t->statusColor()">{{ MpesaTransaction::STATUSES[$t->status] ?? $t->status }}</flux:badge>
                            @if ($t->status === 'failed' && $t->result_description)
                                <div class="mt-1 max-w-[12rem] text-xs text-zinc-500">{{ $t->result_description }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if (in_array($t->status, ['suspense', 'credit'], true))
                                @can('vendor.mpesa.reconcile')
                                    <flux:button size="sm" variant="primary" icon="arrows-right-left" wire:click="startReconcile({{ $t->id }})">Apply</flux:button>
                                @endcan
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="8" class="py-10 text-center text-sm text-zinc-500">No M-Pesa payments yet. Once your Paybill or Till is connected, payments appear here within seconds.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="reconcile" class="w-full max-w-lg">
        @if ($reconciling)
            <form wire:submit="reconcile" class="space-y-5">
                <div>
                    <flux:heading size="lg">Apply {{ $reconciling->receipt }}</flux:heading>
                    <flux:text class="text-sm">
                        KES {{ MoneyMath::format($reconciling->unallocated()) }} from {{ $reconciling->payer_name ?: $reconciling->phone }},
                        account reference "{{ $reconciling->account_reference ?: '—' }}".
                        It settles the customer's unpaid bills (oldest first); anything left stays as their credit.
                        Choosing a prepaid meter issues a token instead.
                    </flux:text>
                </div>
                <flux:select wire:model="target" variant="listbox" searchable placeholder="Choose customer or prepaid meter" label="Belongs to">
                    @foreach ($clients as $c)
                        <flux:select.option value="client:{{ $c->id }}">{{ $c->account_no }} · {{ $c->name }}{{ $c->house_number ? ' · House '.$c->house_number : '' }}{{ $c->phone ? ' · '.$c->phone : '' }}</flux:select.option>
                    @endforeach
                    @foreach ($prepaidMeters as $m)
                        <flux:select.option value="meter:{{ $m->id }}">Prepaid meter {{ $m->serial }}{{ $m->client ? ' · '.$m->client->name : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="target" />
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="reconcile">Apply payment</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
