<?php

use App\Enums\LiabilityCategory;
use App\Models\Liability;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor')] class extends Component
{
    public ?Liability $liability = null;

    public string $name          = '';
    public string $category      = 'loan';
    public string $supplier_id   = '';
    public string $principal     = '';
    public string $amount_paid   = '0';
    public string $interest_rate = '0';
    public string $started_at    = '';
    public string $due_at        = '';
    public string $status        = 'active';
    public string $notes         = '';

    public function mount(?Liability $liability = null): void
    {
        if ($liability && $liability->exists) {
            $this->authorize('update', $liability);
            $this->liability     = $liability;
            $this->name          = $liability->name;
            $this->category      = $liability->category->value;
            $this->supplier_id   = (string) ($liability->supplier_id ?? '');
            $this->principal     = (string) $liability->principal;
            $this->amount_paid   = (string) $liability->amount_paid;
            $this->interest_rate = (string) $liability->interest_rate;
            $this->started_at    = $liability->started_at->format('Y-m-d');
            $this->due_at        = $liability->due_at?->format('Y-m-d') ?? '';
            $this->status        = $liability->status;
            $this->notes         = $liability->notes ?? '';
        } else {
            $this->authorize('create', Liability::class);
            $this->started_at = now()->format('Y-m-d');
        }
    }

    public function title(): string
    {
        return $this->liability ? 'Edit liability' : 'New liability';
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        return [
            'name'          => ['required', 'string', 'max:150'],
            'category'      => ['required', 'in:' . collect(LiabilityCategory::cases())->pluck('value')->implode(',')],
            'supplier_id'   => ['nullable', "exists:suppliers,id,vendor_id,{$vendorId}"],
            'principal'     => ['required', 'numeric', 'min:0.01'],
            'amount_paid'   => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'started_at'    => ['required', 'date'],
            'due_at'        => ['nullable', 'date', 'after_or_equal:started_at'],
            'status'        => ['required', 'in:active,paid,defaulted'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name'          => $this->name,
            'category'      => $this->category,
            'supplier_id'   => $this->supplier_id ?: null,
            'principal'     => $this->principal,
            'amount_paid'   => $this->amount_paid ?: 0,
            'interest_rate' => $this->interest_rate ?: 0,
            'started_at'    => $this->started_at,
            'due_at'        => $this->due_at ?: null,
            'status'        => $this->status,
            'notes'         => $this->notes ?: null,
        ];

        if ($this->liability) {
            $this->liability->update($payload);
            session()->flash('status', 'Liability updated.');
        } else {
            Liability::create($payload);
            session()->flash('status', 'Liability created.');
        }

        $this->redirectRoute('vendor.liabilities.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl space-y-6">
    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-5">
            <flux:heading size="sm">{{ $liability ? 'Edit liability' : 'New liability' }}</flux:heading>

            <flux:input wire:model="name" label="Name / description" />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="category" label="Category">
                    @foreach (LiabilityCategory::cases() as $c)
                        <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="supplier_id" label="Supplier" searchable placeholder="— None —">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($suppliers as $s)
                        <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="principal" type="number" step="0.01" min="0.01" label="Principal amount" />
                <flux:input wire:model="interest_rate" type="number" step="0.01" min="0" max="100" label="Interest rate (% per year)" />
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input type="date" wire:model="started_at" label="Started at" />
                <flux:input type="date" wire:model="due_at" label="Due at" />
                <flux:input wire:model="amount_paid" type="number" step="0.01" min="0" label="Amount paid so far" />
            </div>

            <flux:select wire:model="status" label="Status">
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="paid">Paid</flux:select.option>
                <flux:select.option value="defaulted">Defaulted</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="notes" label="Notes" rows="3" />
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.liabilities.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $liability ? 'Save changes' : 'Create liability' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>