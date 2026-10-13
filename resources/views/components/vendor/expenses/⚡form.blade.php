<?php

use App\Enums\ExpensePaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\WorkOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor')] class extends Component {
    public ?Expense $expense = null;

    public string $expense_category_id = '';
    public string $supplier_id = '';
    public string $work_order_id = '';
    public string $reference = '';
    public string $description = '';
    public string $amount = '';
    public string $tax = '0';
    public string $payment_method = 'cash';
    public string $paid_at = '';

    public function mount(?Expense $expense = null): void
    {
        if ($expense && $expense->exists) {
            $this->authorize('update', $expense);
            $this->expense = $expense;
            $this->expense_category_id = (string) $expense->expense_category_id;
            $this->supplier_id = (string) ($expense->supplier_id ?? '');
            $this->work_order_id = (string) ($expense->work_order_id ?? '');
            $this->reference = $expense->reference ?? '';
            $this->description = $expense->description;
            $this->amount = (string) $expense->amount;
            $this->tax = (string) $expense->tax;
            $this->payment_method = $expense->payment_method->value;
            $this->paid_at = $expense->paid_at->format('Y-m-d\TH:i');
        } else {
            $this->authorize('create', Expense::class);
            $this->paid_at = now()->format('Y-m-d\TH:i');
        }
    }

    public function title(): string
    {
        return $this->expense ? 'Edit expense' : 'Record expense';
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        return [
            'expense_category_id' => ['required', "exists:expense_categories,id,vendor_id,{$vendorId}"],
            'supplier_id' => ['nullable', "exists:suppliers,id,vendor_id,{$vendorId}"],
            'work_order_id' => ['nullable', "exists:work_orders,id,vendor_id,{$vendorId}"],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:' . collect(ExpensePaymentMethod::cases())->pluck('value')->implode(',')],
            'paid_at' => ['required', 'date'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'expense_category_id' => $this->expense_category_id,
            'supplier_id' => $this->supplier_id ?: null,
            'work_order_id' => $this->work_order_id ?: null,
            'reference' => $this->reference ?: null,
            'description' => $this->description,
            'amount' => $this->amount,
            'tax' => $this->tax ?: 0,
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at,
        ];

        if ($this->expense) {
            $this->expense->update($payload);
            session()->flash('status', 'Expense updated.');
        } else {
            $payload['recorded_by'] = auth()->id();
            Expense::create($payload);
            session()->flash('status', 'Expense recorded.');
        }

        $this->redirectRoute('vendor.expenses.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'workOrders' => WorkOrder::orderByDesc('id')
                ->limit(100)
                ->get(['id', 'number', 'title']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl space-y-6">

    <form wire:submit="save" class="space-y-6">

        <flux:card class="space-y-5">
            <flux:heading size="sm">{{ $expense ? 'Edit expense' : 'Record expense' }}</flux:heading>

            <flux:select wire:model="expense_category_id" label="Category" searchable placeholder="Choose Category">
                @foreach ($categories as $c)
                    <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="description" label="Description" placeholder="e.g. Replacement meter fittings" />

            <flux:select wire:model="supplier_id" label="Supplier (optional)" searchable placeholder="— None —">
                <flux:select.option value="">— None —</flux:select.option>
                @foreach ($suppliers as $s)
                    <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="work_order_id" label="Link to work order (optional)" searchable
                placeholder="— None —">
                <flux:select.option value="">— None —</flux:select.option>
                @foreach ($workOrders as $wo)
                    <flux:select.option value="{{ $wo->id }}">
                        {{ $wo->number }} · {{ $wo->title }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="reference" label="Reference (optional)"
                placeholder="Receipt no, invoice no, cheque no" />
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="sm">Amount & payment</flux:heading>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model.live="amount" type="number" step="0.01" min="0.01" label="Amount" />
                <flux:input wire:model.live="tax" type="number" step="0.01" min="0" label="Tax (optional)" />
            </div>

            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4 text-sm flex items-center justify-between">
                <span class="text-zinc-600 dark:text-zinc-400">Total</span>
                <span class="font-mono font-semibold">
                    {{ number_format((float) $amount + (float) $tax, 2) }}
                </span>
            </div>

            <flux:select wire:model="payment_method" label="Payment method">
                @foreach (ExpensePaymentMethod::cases() as $m)
                    <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="datetime-local" wire:model="paid_at" label="Paid at" />
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.expenses.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $expense ? 'Save changes' : 'Record expense' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
