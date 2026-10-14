<?php

use App\Models\ProjectExpense;
use App\Models\ProjectSupplier;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Project suppliers — drilling contractors, pipe and casing suppliers,
 * equipment hire and so on. Independent of Water Billing's supplier list.
 * Balances come from approved project expenses (billed, paid, still owed).
 */
new #[Layout('layouts.vendor'), Title('Project suppliers')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)] public string $search = '';
    #[Url(history: true)] public string $category = '';
    #[Url(history: true)] public string $status = 'active';

    public ?int $editingId = null;
    public string $name = '';
    public string $supplierCategory = '';
    public string $contact_person = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $kra_pin = '';
    public string $payment_terms = '';
    public string $notes = '';
    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('projects.view'), 403);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'category', 'status'], true)) {
            $this->resetPage();
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('projects.suppliers.manage'), 403);
    }

    public function startAdd(): void
    {
        $this->authorizeManage();
        $this->reset('editingId', 'name', 'supplierCategory', 'contact_person', 'phone', 'email', 'address', 'kra_pin', 'payment_terms', 'notes');
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('supplier')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorizeManage();
        $s = ProjectSupplier::findOrFail($id);
        $this->editingId = $s->id;
        $this->supplierCategory = (string) $s->category;
        foreach (['name', 'contact_person', 'phone', 'email', 'address', 'kra_pin', 'payment_terms', 'notes'] as $field) {
            $this->{$field} = (string) $s->{$field};
        }
        $this->is_active = $s->is_active;
        $this->resetValidation();
        Flux::modal('supplier')->show();
    }

    public function save(): void
    {
        $this->authorizeManage();
        $vendorId = auth()->user()->vendor_id;

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('project_suppliers', 'name')->where('vendor_id', $vendorId)->ignore($this->editingId)],
            'supplierCategory' => ['nullable', Rule::in(array_keys(ProjectSupplier::CATEGORIES))],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'kra_pin' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], [], ['supplierCategory' => 'category', 'kra_pin' => 'KRA PIN']);

        $data = [
            'name' => trim($this->name),
            'category' => $this->supplierCategory ?: null,
            'contact_person' => $this->contact_person ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'kra_pin' => $this->kra_pin ? strtoupper($this->kra_pin) : null,
            'payment_terms' => $this->payment_terms ?: null,
            'notes' => $this->notes ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            ProjectSupplier::findOrFail($this->editingId)->update($data);
        } else {
            ProjectSupplier::create($data + ['vendor_id' => $vendorId]);
        }

        Flux::modal('supplier')->close();
        Flux::toast(variant: 'success', text: $this->editingId ? 'Supplier updated.' : 'Supplier added.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $s = ProjectSupplier::withCount(['expenses', 'materialMovements', 'equipment'])->findOrFail($id);

        if ($s->expenses_count || $s->material_movements_count || $s->equipment_count) {
            $s->update(['is_active' => false]);
            Flux::toast(variant: 'warning', heading: 'Supplier deactivated', text: 'It has project history, so it is kept for the records and hidden from new entries.');

            return;
        }

        $s->delete();
        Flux::toast(variant: 'success', text: 'Supplier removed.');
    }

    public function with(): array
    {
        $suppliers = ProjectSupplier::query()
            ->withCount('materialMovements')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('contact_person', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20);

        // Balances from approved project expenses (vendor-scoped through the project).
        $approved = ProjectExpense::query()
            ->whereIn('supplier_id', $suppliers->pluck('id'))
            ->where('status', 'approved')
            ->whereHas('project')
            ->get(['supplier_id', 'amount', 'paid_amount'])
            ->groupBy('supplier_id');

        $balances = $suppliers->getCollection()->mapWithKeys(function ($s) use ($approved) {
            $rows = $approved->get($s->id, collect());
            $billed = MoneyMath::sumColumn($rows, 'amount');
            $paid = MoneyMath::sumColumn($rows, 'paid_amount');

            return [$s->id => ['billed' => $billed, 'paid' => $paid, 'owed' => MoneyMath::sub($billed, $paid)]];
        });

        return [
            'suppliers' => $suppliers,
            'balances' => $balances,
            'totalOwed' => MoneyMath::sumColumn($balances->values(), 'owed'),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-amber flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm"><flux:icon.truck variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="lg">Project suppliers</flux:heading>
                <flux:text class="text-sm">Drilling contractors, pipe and casing suppliers, equipment hire and more. Separate from the Water Billing supplier list.</flux:text>
            </div>
        </div>
        @can('projects.suppliers.manage')
            <flux:button variant="primary" icon="plus" wire:click="startAdd">Add supplier</flux:button>
        @endcan
    </div>

    <div class="app-card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search name, contact or phone" class="sm:col-span-2" />
        <flux:select wire:model.live="category">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach (ProjectSupplier::CATEGORIES as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
        </flux:select>
        <flux:select wire:model.live="status">
            <flux:select.option value="active">Active</flux:select.option>
            <flux:select.option value="inactive">Inactive</flux:select.option>
            <flux:select.option value="all">All</flux:select.option>
        </flux:select>
    </div>

    <div class="flex items-center justify-between text-sm">
        <flux:text>Owed to suppliers on this page: <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ MoneyMath::format($totalOwed) }}</span></flux:text>
    </div>

    <flux:table :paginate="$suppliers">
        <flux:table.columns>
            <flux:table.column>Supplier</flux:table.column>
            <flux:table.column>Contact</flux:table.column>
            <flux:table.column align="end">Billed</flux:table.column>
            <flux:table.column align="end">Paid</flux:table.column>
            <flux:table.column align="end">Owed</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($suppliers as $s)
                @php $b = $balances[$s->id]; @endphp
                <flux:table.row :key="$s->id">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->name }}</div>
                        <div class="flex flex-wrap items-center gap-1 text-xs text-zinc-500">
                            <span>{{ $s->categoryLabel() }}</span>
                            @if ($s->kra_pin) <span>· PIN {{ $s->kra_pin }}</span> @endif
                            @if ($s->material_movements_count) <span>· {{ $s->material_movements_count }} material entries</span> @endif
                            @unless ($s->is_active) <flux:badge size="sm" color="zinc">Inactive</flux:badge> @endunless
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-sm">
                        <div>{{ $s->contact_person ?: '—' }}</div>
                        <div class="text-xs text-zinc-500">
                            @if ($s->phone) <a href="tel:{{ preg_replace('/\s+/', '', $s->phone) }}" class="hover:underline">{{ $s->phone }}</a> @endif
                            @if ($s->email) · {{ $s->email }} @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format($b['billed']) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ MoneyMath::format($b['paid']) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono {{ MoneyMath::isPositive($b['owed']) ? 'font-semibold text-amber-600' : '' }}">{{ MoneyMath::format($b['owed']) }}</flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        @can('projects.suppliers.manage')
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $s->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" class="!text-rose-600" wire:click="delete({{ $s->id }})"
                                wire:confirm="Remove {{ $s->name }}? Suppliers with project history are deactivated instead." />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="6" class="text-center text-zinc-500">No project suppliers yet.</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="supplier" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Edit supplier' : 'Add a project supplier' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" class="sm:col-span-2" />
                <flux:select wire:model="supplierCategory" label="Category">
                    <flux:select.option value="">— Choose —</flux:select.option>
                    @foreach (ProjectSupplier::CATEGORIES as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
                </flux:select>
                <flux:input wire:model="contact_person" label="Contact person" />
                <flux:input wire:model="phone" label="Phone" />
                <flux:input wire:model="email" type="email" label="Email" />
                <flux:input wire:model="kra_pin" label="KRA PIN" placeholder="P051234567X" />
                <flux:input wire:model="payment_terms" label="Payment terms" placeholder="e.g. 30 days, cash on delivery" />
                <flux:input wire:model="address" label="Address / location" class="sm:col-span-2" />
                <flux:textarea wire:model="notes" label="Notes" rows="2" class="sm:col-span-2" />
            </div>
            <flux:switch wire:model="is_active" label="Active (shown when recording expenses and materials)" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
