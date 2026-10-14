<?php

use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\Supplier;
use App\Services\Projects\ExpenseWorkflow;
use App\Services\Projects\ProjectFinancials;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads, WithPagination;

    public Project $project;

    #[Url(as: 'expense_status')] public string $filterStatus = '';
    #[Url(as: 'expense_category')] public string $filterCategory = '';

    public string $category = 'other';
    public string $description = '';
    public string $justification = '';
    public string $expense_date = '';
    public string $amount = '';
    public string $supplier_id = '';
    public string $payee_name = '';
    public string $payment_method = '';
    public string $reference = '';
    public bool $is_settlement = false;
    public string $notes = '';
    public $receipt = null;

    public ?int $actingId = null;
    public string $rejection_reason = '';
    public string $payment_amount = '';
    public string $payment_via = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function updating($name): void
    {
        if (str_starts_with($name, 'filter')) {
            $this->resetPage();
        }
    }

    public function startSubmit(): void
    {
        $this->authorize('submitExpense', $this->project);
        $this->reset('description', 'justification', 'amount', 'supplier_id', 'payee_name', 'payment_method', 'reference', 'is_settlement', 'notes', 'receipt');
        $this->category = 'other';
        $this->expense_date = today()->toDateString();
        $this->resetValidation();
        Flux::modal('expense')->show();
    }

    public function submit(): void
    {
        $this->authorize('submitExpense', $this->project);
        $data = $this->validate([
            'category' => ['required', Rule::in(array_keys(config('projects.expense_categories')))],
            'description' => ['required', 'string', 'max:255'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'supplier_id' => ['nullable', 'exists:suppliers,id,vendor_id,'.$this->project->vendor_id],
            'payee_name' => ['nullable', 'required_without:supplier_id', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::in(array_keys(ProjectExpense::PAYMENT_METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'is_settlement' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], ['payee_name.required_without' => 'Choose a supplier or type who was paid.']);

        $data = collect($data)->except('receipt')->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['amount'] = MoneyMath::str($this->amount);
        if ($this->receipt) {
            $data['receipt_path'] = $this->receipt->store("projects/{$this->project->id}/receipts", 'public');
        }

        $expense = app(ExpenseWorkflow::class)->submit($this->project, $data);

        Flux::modal('expense')->close();
        Flux::toast(variant: 'success', heading: 'Expense submitted', text: $expense->isOverApprovalLimit() ? 'Above the approval limit — flagged for the approver.' : 'Waiting for approval.');
        $this->dispatch('project-changed');
    }

    protected function expense(?int $id): ProjectExpense
    {
        return $this->project->expenses()->findOrFail($id);
    }

    public function approve(int $id): void
    {
        $this->authorize('approveExpense', $this->project);
        app(ExpenseWorkflow::class)->approve($this->expense($id));
        Flux::toast(variant: 'success', text: 'Expense approved.');
        $this->dispatch('project-changed');
    }

    public function startReject(int $id): void
    {
        $this->authorize('approveExpense', $this->project);
        $this->actingId = $this->expense($id)->id;
        $this->rejection_reason = '';
        $this->resetValidation();
        Flux::modal('reject-expense')->show();
    }

    public function reject(): void
    {
        $this->authorize('approveExpense', $this->project);
        $this->validate(['rejection_reason' => ['required', 'string', 'max:255']], [], ['rejection_reason' => 'reason']);
        app(ExpenseWorkflow::class)->reject($this->expense($this->actingId), $this->rejection_reason);
        Flux::modal('reject-expense')->close();
        Flux::toast(text: 'Expense rejected.');
        $this->dispatch('project-changed');
    }

    public function startPayment(int $id): void
    {
        $this->authorize('approveExpense', $this->project);
        $expense = $this->expense($id);
        $this->actingId = $expense->id;
        $this->payment_amount = $expense->outstanding();
        $this->payment_via = $expense->payment_method ?? '';
        $this->resetValidation();
        Flux::modal('pay-expense')->show();
    }

    public function pay(): void
    {
        $this->authorize('approveExpense', $this->project);
        $this->validate([
            'payment_amount' => ['required', 'numeric', 'gt:0'],
            'payment_via' => ['nullable', Rule::in(array_keys(ProjectExpense::PAYMENT_METHODS))],
        ], [], ['payment_amount' => 'amount']);

        app(ExpenseWorkflow::class)->recordPayment($this->expense($this->actingId), MoneyMath::str($this->payment_amount), $this->payment_via ?: null);

        Flux::modal('pay-expense')->close();
        Flux::toast(variant: 'success', text: 'Payment recorded.');
        $this->dispatch('project-changed');
    }

    public function with(): array
    {
        return [
            'summary' => app(ProjectFinancials::class)->summary($this->project->fresh()),
            'expenses' => $this->project->expenses()->with(['supplier', 'submitter', 'approver'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
                ->latest('expense_date')->latest('id')->paginate(15),
            'suppliers' => Supplier::query()->where('vendor_id', $this->project->vendor_id)->orderBy('name')->get(['id', 'name']),
        ];
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">Expenses</flux:heading>
            <flux:text class="text-sm">Submit → approve → pay. Approved direct expenses are project cost; settlements only move cash.</flux:text>
        </div>
        @can('submitExpense', $project)
            <flux:button variant="primary" icon="plus" wire:click="startSubmit">Submit expense</flux:button>
        @endcan
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['Submitted (not rejected)', $summary['expenses_submitted']],
            ['Approved', $summary['expenses_approved']],
            ['Paid', $summary['paid']],
            ['Outstanding', $summary['unpaid_approved']],
            ['Remaining budget', $summary['remaining_budget']],
        ] as [$label, $amount])
            <div class="app-card p-4">
                <div class="text-xs text-zinc-500">{{ $label }}</div>
                <div class="font-mono text-lg font-semibold {{ MoneyMath::isNegative($amount) ? 'text-rose-600' : 'text-zinc-900 dark:text-zinc-100' }}">{{ MoneyMath::format($amount) }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:select wire:model.live="filterStatus" class="max-w-52">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (ProjectExpense::STATUSES as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
        </flux:select>
        <flux:select wire:model.live="filterCategory" class="max-w-60">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach (config('projects.expense_categories') as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$expenses">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Expense</flux:table.column>
            <flux:table.column>Payee</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Amount</flux:table.column>
            <flux:table.column align="end">Paid</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($expenses as $e)
                <flux:table.row :key="$e->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $e->expense_date->format('d M Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $e->description }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ $e->categoryLabel() }}@if ($e->reference) · {{ $e->reference }}@endif
                            · by {{ $e->submitter?->name ?? '—' }}
                            @if ($e->receipt_path) · <a href="{{ Storage::disk('public')->url($e->receipt_path) }}" target="_blank" class="text-brand-700 hover:underline dark:text-brand-300">receipt</a> @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @if ($e->is_settlement) <flux:badge size="sm" color="sky">Settlement · not cost</flux:badge> @endif
                            @if ($e->isOverApprovalLimit()) <flux:badge size="sm" color="rose">Over approval limit</flux:badge> @endif
                        </div>
                        @if ($e->rejection_reason) <div class="text-xs text-rose-600">{{ $e->rejection_reason }}</div> @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $e->payee() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="['submitted' => 'amber', 'approved' => 'emerald', 'rejected' => 'rose'][$e->status]">{{ ProjectExpense::STATUSES[$e->status] }}</flux:badge>
                        @if ($e->status === 'approved')
                            <div class="mt-1 text-xs text-zinc-500">{{ ucfirst($e->paymentStatus()) }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format((string) $e->amount) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format((string) $e->paid_amount) }}</flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        @can('approveExpense', $project)
                            @if ($e->status === 'submitted')
                                <flux:button size="xs" variant="primary" wire:click="approve({{ $e->id }})">Approve</flux:button>
                                <flux:button size="xs" variant="ghost" wire:click="startReject({{ $e->id }})">Reject</flux:button>
                            @elseif ($e->status === 'approved' && $e->paymentStatus() !== 'paid')
                                <flux:button size="xs" icon="banknotes" wire:click="startPayment({{ $e->id }})">Pay</flux:button>
                            @endif
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="7" class="text-center text-zinc-500">No expenses yet.</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="expense" class="w-full max-w-2xl">
        <form wire:submit="submit" class="space-y-5">
            <flux:heading size="lg">Submit an expense</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="category" label="Category" class="sm:col-span-2">
                    @foreach (config('projects.expense_categories') as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
                </flux:select>
                <flux:input type="date" wire:model="expense_date" label="Date" />
            </div>
            <flux:input wire:model="description" label="Description" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input type="number" step="0.01" wire:model="amount" label="Amount" />
                <flux:select wire:model="supplier_id" label="Supplier">
                    <flux:select.option value="">— Not a listed supplier —</flux:select.option>
                    @foreach ($suppliers as $s) <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option> @endforeach
                </flux:select>
                <flux:input wire:model="payee_name" label="…or payee name" placeholder="Employee or vendor" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="payment_method" label="Payment method">
                    <flux:select.option value="">— Not paid yet —</flux:select.option>
                    @foreach (ProjectExpense::PAYMENT_METHODS as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
                </flux:select>
                <flux:input wire:model="reference" label="Invoice / receipt number" />
            </div>
            <flux:textarea wire:model="justification" label="Justification" rows="2" />
            <flux:checkbox wire:model="is_settlement" label="This bill pays for materials, labour or equipment already recorded on this project"
                description="It will count toward cash paid and payables, but not as cost again — so nothing is double-counted." />
            <flux:input type="file" wire:model="receipt" label="Receipt / invoice" />
            <flux:textarea wire:model="notes" label="Notes" rows="2" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Submit</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="reject-expense" class="w-full max-w-md">
        <form wire:submit="reject" class="space-y-5">
            <flux:heading size="lg">Reject expense</flux:heading>
            <flux:input wire:model="rejection_reason" label="Reason" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Reject</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="pay-expense" class="w-full max-w-md">
        <form wire:submit="pay" class="space-y-5">
            <div>
                <flux:heading size="lg">Record a payment</flux:heading>
                <flux:subheading>Part payments are fine — the balance stays outstanding.</flux:subheading>
            </div>
            <flux:input type="number" step="0.01" wire:model="payment_amount" label="Amount paid" />
            <flux:error name="payment" />
            <flux:select wire:model="payment_via" label="Paid via">
                <flux:select.option value="">—</flux:select.option>
                @foreach (ProjectExpense::PAYMENT_METHODS as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
            </flux:select>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Record payment</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
