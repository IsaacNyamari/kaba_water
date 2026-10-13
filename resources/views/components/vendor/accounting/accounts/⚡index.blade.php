<?php

use App\Enums\AccountType;
use App\Livewire\Concerns\UsesAccounting;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Chart of Accounts')] class extends Component {
    use UsesAccounting, WithPagination;

    #[Url(history: true)]
    public string $type = '';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public bool $showInactive = true;

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->bootAccounting();
    }

    public function updating($field): void
    {
        if (in_array($field, ['type', 'search', 'showInactive'], true)) {
            $this->resetPage();
        }
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('accounting.post');
        $account = ChartOfAccount::findOrFail($id);

        $account->update(['is_active' => ! $account->is_active]);

        Flux::toast(variant: 'success', heading: $account->is_active ? 'Activated' : 'Deactivated',
            text: "{$account->code} {$account->name}" . ($account->is_active ? ' can receive postings again.' : ' will refuse new postings.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('accounting.post');
        $this->deletingId = ChartOfAccount::findOrFail($id)->id;
        Flux::modal('delete-account')->show();
    }

    public function delete(): void
    {
        $this->authorize('accounting.post');
        $account = ChartOfAccount::findOrFail($this->deletingId);

        if ($account->is_system) {
            Flux::toast(variant: 'danger', heading: 'Cannot delete', text: 'Standard accounts can be deactivated, not deleted.');
        } elseif (JournalLine::where('account_id', $account->id)->exists()) {
            Flux::toast(variant: 'danger', heading: 'Cannot delete', text: 'This account has ledger lines. Deactivate it instead.');
        } else {
            $account->delete();
            Flux::toast(variant: 'success', heading: 'Deleted', text: "{$account->code} {$account->name} deleted.");
        }

        $this->deletingId = null;
        Flux::modal('delete-account')->close();
    }

    public function with(): array
    {
        return [
            'accounts' => ChartOfAccount::query()
                ->with('parent')
                ->withCount('lines')
                ->when($this->type, fn ($q) => $q->byType($this->type))
                ->when(! $this->showInactive, fn ($q) => $q->active())
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%")))
                ->orderBy('code')
                ->paginate(50),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="list-bullet" title="Chart of accounts"
            subtitle="Every posting lands in one of these accounts. Standard accounts are locked; add your own beside them.">
            <x-slot:actions>
                @can('accounting.post')
                    <flux:button href="{{ route('vendor.accounting.accounts.create') }}" variant="primary" icon="plus" wire:navigate>
                        New account
                    </flux:button>
                @endcan
            </x-slot:actions>
        </x-accounting.card-header>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Code or name…" class="sm:w-64" />
            <flux:select wire:model.live="type" class="sm:w-48">
                <flux:select.option value="">All types</flux:select.option>
                @foreach (AccountType::cases() as $t)
                    <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:checkbox wire:model.live="showInactive" label="Show inactive" />
        </div>
    </flux:card>

    <flux:table :paginate="$accounts">
        <flux:table.columns>
            <flux:table.column>Code</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Normal side</flux:table.column>
            <flux:table.column align="end">Lines</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($accounts as $a)
                <flux:table.row :key="$a->id" class="{{ $a->is_active ? '' : 'opacity-60' }}">
                    <flux:table.cell class="font-mono">{{ $a->code }}</flux:table.cell>
                    <flux:table.cell variant="strong">
                        {{ $a->name }}
                        @if ($a->parent)
                            <div class="text-xs font-normal text-zinc-500">under {{ $a->parent->code }} {{ $a->parent->name }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell><x-accounting.type-badge :type="$a->type" /></flux:table.cell>
                    <flux:table.cell class="text-sm">{{ $a->normal_side->label() }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ number_format($a->lines_count) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($a->is_system)
                            <flux:badge size="sm" color="zinc" icon="lock-closed">Standard</flux:badge>
                        @endif
                        @unless ($a->is_active)
                            <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                        @endunless
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="book-open"
                                href="{{ route('vendor.accounting.reports.general-ledger', ['account' => $a->id]) }}" wire:navigate
                                title="General ledger" />
                            @can('accounting.post')
                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    href="{{ route('vendor.accounting.accounts.edit', $a) }}" wire:navigate />
                                <flux:button size="sm" variant="ghost" :icon="$a->is_active ? 'pause-circle' : 'play-circle'"
                                    wire:click="toggleActive({{ $a->id }})" :title="$a->is_active ? 'Deactivate' : 'Activate'" />
                                @if (! $a->is_system && $a->lines_count === 0)
                                    <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                        wire:click="confirmDelete({{ $a->id }})" />
                                @endif
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500 py-10">No accounts match.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-account" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete account?</flux:heading>
                <flux:subheading>Only unused, non-standard accounts can be deleted.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
