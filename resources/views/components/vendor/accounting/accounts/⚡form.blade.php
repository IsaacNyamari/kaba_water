<?php

use App\Enums\AccountType;
use App\Livewire\Concerns\UsesAccounting;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountsService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Account')] class extends Component {
    use UsesAccounting;

    public ?ChartOfAccount $account = null;

    public string $code = '';
    public string $name = '';
    public string $type = 'expense';
    public string $normal_side = 'debit';
    public ?int $parent_id = null;
    public bool $is_active = true;
    public string $description = '';

    public function mount(?ChartOfAccount $account = null): void
    {
        $this->bootAccounting('accounting.post');

        if ($account && $account->exists) {
            abort_unless($account->vendor_id === $this->vendorId(), 404);
            $this->account = $account;
            $this->fill([
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'normal_side' => $account->normal_side->value,
                'parent_id' => $account->parent_id,
                'is_active' => $account->is_active,
                'description' => $account->description ?? '',
            ]);
        }
    }

    /** Picking a type suggests its usual normal side. */
    public function updatedType(): void
    {
        $this->normal_side = AccountType::from($this->type)->normalSide()->value;
    }

    protected function rules(): array
    {
        $vendorId = $this->vendorId();

        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[0-9A-Za-z.\-]+$/',
                Rule::unique('chart_of_accounts', 'code')->where('vendor_id', $vendorId)->ignore($this->account?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'normal_side' => ['required', 'in:debit,credit'],
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('vendor_id', $vendorId)],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(): void
    {
        $this->authorize('accounting.post');
        $this->validate();

        if ($this->account?->is_system) {
            // Standard accounts keep their code, type and side — postings depend on them.
            $locked = ['code' => $this->account->code, 'type' => $this->account->type->value, 'normal_side' => $this->account->normal_side->value];
            if ($this->code !== $locked['code'] || $this->type !== $locked['type'] || $this->normal_side !== $locked['normal_side']) {
                $this->addError('code', 'The code, type and normal side of a standard account cannot be changed.');
                return;
            }
        }

        if ($this->account && ! $this->is_active && app(ChartOfAccountsService::class)->isRequiredForPosting($this->account)) {
            $this->addError('is_active', 'This account is used by automatic postings (invoices, payments or expenses) and must stay active.');
            return;
        }

        if ($this->account && $this->parent_id === $this->account->id) {
            $this->addError('parent_id', 'An account cannot be its own parent.');
            return;
        }

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'normal_side' => $this->normal_side,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'description' => $this->description ?: null,
        ];

        if ($this->account) {
            $this->account->update($data);
        } else {
            ChartOfAccount::create($data);
        }

        Flux::toast(variant: 'success', heading: 'Saved', text: "{$this->code} {$this->name}");
        $this->redirectRoute('vendor.accounting.accounts.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'parents' => ChartOfAccount::where('type', $this->type)
                ->when($this->account, fn ($q) => $q->whereKeyNot($this->account->id))
                ->orderBy('code')->get(['id', 'code', 'name']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 max-w-3xl">
    <form wire:submit="save">
        <flux:card class="space-y-6">
            <x-accounting.card-header icon="list-bullet" :title="$account ? 'Edit account' : 'New account'"
                :subtitle="$account?->is_system ? 'Standard account — only the name, parent, status and description can change.' : 'Codes must be unique in your chart.'" />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="code" label="Code" placeholder="e.g. 5250" :disabled="(bool) $account?->is_system" />
                <flux:input wire:model="name" label="Name" />

                <flux:select wire:model.live="type" label="Type" :disabled="(bool) $account?->is_system">
                    @foreach (AccountType::cases() as $t)
                        <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="normal_side" label="Normal side" :disabled="(bool) $account?->is_system"
                    description="Credit for contra accounts like accumulated depreciation.">
                    <flux:select.option value="debit">Debit</flux:select.option>
                    <flux:select.option value="credit">Credit</flux:select.option>
                </flux:select>

                <flux:select wire:model="parent_id" label="Parent (optional)" placeholder="None">
                    <flux:select.option value="">None</flux:select.option>
                    @foreach ($parents as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->code }} · {{ $p->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex items-end">
                    <div>
                        <flux:checkbox wire:model="is_active" label="Active (can receive postings)" />
                        <flux:error name="is_active" />
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="description" label="Description" rows="2" />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" href="{{ route('vendor.accounting.accounts.index') }}" wire:navigate>Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Save account</flux:button>
            </div>
        </flux:card>
    </form>
</div>
