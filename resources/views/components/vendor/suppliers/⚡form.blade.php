<?php

use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor')] class extends Component {
    public ?Supplier $supplier = null;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $notes = '';
    public string $status = 'active';

    public function mount(?Supplier $supplier = null): void
    {
        if ($supplier && $supplier->exists) {
            $this->authorize('update', $supplier);
            $this->supplier = $supplier;
            $this->name = $supplier->name;
            $this->email = $supplier->email ?? '';
            $this->phone = $supplier->phone ?? '';
            $this->address = $supplier->address ?? '';
            $this->notes = $supplier->notes ?? '';
            $this->status = $supplier->status;
        } else {
            $this->authorize('create', Supplier::class);
        }
    }

    public function title(): string
    {
        return $this->supplier ? 'Edit supplier' : 'New supplier';
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'notes' => $this->notes ?: null,
            'status' => $this->status,
        ];

        if ($this->supplier) {
            $this->supplier->update($payload);
            session()->flash('status', 'Supplier updated.');
        } else {
            Supplier::create($payload);
            session()->flash('status', 'Supplier created.');
        }

        $this->redirectRoute('vendor.suppliers.index', navigate: true);
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl space-y-6">
    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-5">
            <flux:heading size="sm">{{ $supplier ? 'Edit supplier' : 'New supplier' }}</flux:heading>

            <flux:input wire:model="name" label="Name" />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="phone" label="Phone" />
                <flux:input wire:model="email" type="email" label="Email" />
            </div>

            <flux:textarea wire:model="address" label="Address" rows="2" />
            <flux:textarea wire:model="notes" label="Notes" rows="3" />

            <flux:select wire:model="status" label="Status">
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.suppliers.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $supplier ? 'Save changes' : 'Create supplier' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
