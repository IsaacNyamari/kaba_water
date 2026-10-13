<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePdfService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.vendor', ['title' => 'Record payment'])] class extends Component {
    public ?int $invoice_id = null;
    public string $amount = '';
    public string $method = 'cash';
    public string $reference = '';
    public string $paid_at = '';

    public function mount(): void
    {
        $this->authorize('create', Payment::class);

        $this->paid_at = now()->format('Y-m-d\TH:i');

        if ($invoiceId = request()->integer('invoice')) {
            $this->invoice_id = $invoiceId;
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                $this->amount = (string) $invoice->balance();
            }
        }
    }

    #[Computed]
    public function invoices()
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->with('client')
            ->latest('issued_at')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function selected()
    {
        return $this->invoice_id ? $this->invoices->firstWhere('id', $this->invoice_id) : null;
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        return [
            'invoice_id' => ['required', "exists:invoices,id,vendor_id,{$vendorId}"],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:' . collect(PaymentMethod::cases())->pluck('value')->implode(',')],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    public function save(InvoicePdfService $pdf): void
    {
        $this->authorize('create', Payment::class);
        $this->validate();

        $invoice = Invoice::findOrFail($this->invoice_id);
        $this->authorize('view', $invoice);

        if (! $invoice->status->isPayable()) {
            $this->addError('invoice_id', 'Only sent or overdue invoices can receive payments.');
            return;
        }

        $amount = (float) $this->amount;

        if ($amount > $invoice->balance() + 0.001) {
            $this->addError('amount', 'Amount exceeds the outstanding balance of ' . number_format($invoice->balance(), 2) . '.');
            return;
        }

        try {
            DB::transaction(function () use ($invoice, $amount) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'amount' => $amount,
                    'method' => $this->method,
                    'reference' => $this->reference ?: null,
                    'paid_at' => $this->paid_at,
                    'recorded_by' => auth()->id(),
                ]);

                // Recompute amount_paid + status (paid / sent / overdue)
                $invoice->syncPayments();
            });
        } catch (\App\Services\Accounting\Exceptions\AccountingException $e) {
            Flux::toast(variant: 'danger', heading: 'Cannot record payment', text: $e->getMessage());
            return;
        }

        // Keep the stored PDF in step with the new balance / PAID stamp.
        rescue(fn () => $pdf->regenerate($invoice));

        Flux::toast(variant: 'success', heading: 'Payment recorded', text: number_format($amount, 2) . ' against ' . $invoice->number);

        $this->redirectRoute('vendor.payments.index', navigate: true);
    }

    public function updatedInvoiceId(): void
    {
        // Pre-fill amount with the remaining balance when a new invoice is chosen
        if ($this->selected) {
            $this->amount = (string) $this->selected->balance();
        }
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl">
    <form wire:submit="save" class="space-y-6">

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-5">
            <flux:heading size="sm">Payment details</flux:heading>

            <flux:select wire:model.live="invoice_id" label="Invoice" searchable placeholder="— Pick an unpaid invoice —">
            <flux:select.option> choose</flux:select.option>   
            @forelse ($this->invoices as $inv)
                    <flux:select.option value="{{ $inv->id }}">
                        {{ $inv->number }} ·
                        {{ $inv->client->name }} ·
                        balance {{ number_format($inv->balance(), 2) }}
                    </flux:select.option>
                @empty
                    <flux:select.option value="" disabled>
                        No unpaid invoices available
                    </flux:select.option>
                @endforelse
            </flux:select>
            @error('invoice_id')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror

            @if ($this->selected)
                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4 text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Client</span>
                        <span class="font-medium">{{ $this->selected->client->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Invoice total</span>
                        <span class="font-mono">{{ number_format($this->selected->total, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Paid so far</span>
                        <span
                            class="font-mono text-accent-600">{{ number_format($this->selected->amount_paid, 2) }}</span>
                    </div>
                    <div
                        class="flex justify-between font-semibold text-brand-700 border-t border-zinc-200 dark:border-zinc-700 pt-2">
                        <span>Balance</span>
                        <span class="font-mono">{{ number_format($this->selected->balance(), 2) }}</span>
                    </div>
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="amount" type="number" step="0.01" min="0.01" label="Amount" />
                @error('amount')
                    <p class="text-xs text-rose-600 mt-1 sm:col-span-2">{{ $message }}</p>
                @enderror

                <flux:select wire:model="method" label="Method">
                    @foreach (PaymentMethod::cases() as $m)
                        <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model="reference" label="Reference"
                description="M-Pesa code, cheque number, transaction ID, etc." />

            <flux:input type="datetime-local" wire:model="paid_at" label="Paid at" />
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.payments.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                <span wire:loading.remove>Record payment</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
