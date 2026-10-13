<?php

use App\Models\Client;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    public ?int $deleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function setDeleteId(Client $client): void
    {
        $this->deleteId = $client->id;
        Flux::modal('delete-client')->show();
    }

    public function delete(): void
    {
        if (!$this->deleteId) {
            return;
        }

        $client = Client::findOrFail($this->deleteId);
        $this->authorize('delete', $client);

        if ($client->invoices()->exists()) {
            session()->flash('error', 'Cannot delete a client that has invoices.');
            $this->deleteId = null;
            Flux::modal('delete-client')->close();
            return;
        }

        $client->delete();

        $this->deleteId = null;
        Flux::modal('delete-client')->close();
        session()->flash('status', 'Client deleted.');
    }

    public function with(): array
    {
        return [
            'clients' => Client::query()
                ->when(
                    $this->search,
                    fn($q) => $q->where(
                        fn($q) => $q
                            ->where('name', 'like', "%{$this->search}%")
                            ->orWhere('account_no', 'like', "%{$this->search}%")
                            ->orWhere('phone', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%"),
                    ),
                )
                ->when($this->status, fn($q) => $q->where('status', $this->status))
                ->withCount('meters')
                ->latest()
                ->paginate(7),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex-1 flex flex-col sm:flex-row gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search by name, account, phone, email…" class="sm:w-80" />
            <flux:select wire:model.live="status" class="sm:w-48">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </div>

        <flux:button wire:navigate href="{{ route('vendor.clients.upload') }}" variant="primary" color="emerald"
            icon="cloud-arrow-up">
            Import csv, xlsx
        </flux:button>
        <flux:button href="{{ route('vendor.clients.create') }}" variant="primary" icon="plus">
            Add client
        </flux:button>
        <livewire:export-clients />
    </div>

    {{-- Flash messages --}}
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
    @endif

    {{-- Table --}}
    <flux:table :paginate="$clients">
        <flux:table.columns>
            <flux:table.column>Account</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Contact</flux:table.column>
            <flux:table.column>Meters</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($clients as $client)
                <flux:table.row :key="$client->id">
                    <flux:table.cell class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                        {{ $client->account_no }}
                    </flux:table.cell>

                    <flux:table.cell variant="strong">
                        {{ $client->name }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($client->phone)
                            <div>{{ $client->phone }}</div>
                        @endif
                        @if ($client->email)
                            <div class="text-xs text-zinc-500">{{ $client->email }}</div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $client->meters_count }}
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        <flux:badge :color="$client->status === 'active' ? 'emerald' : 'zinc'" size="sm">
                            {{ ucfirst($client->status) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1 items-center">
                            <flux:button variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.clients.edit', $client) }}" />
                            <flux:modal.trigger wire:click="setDeleteId({{ $client->id }})">
                                <flux:icon.trash color="red" size="sm" />
                            </flux:modal.trigger>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-10">
                        No clients found.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Delete confirmation modal --}}
    <flux:modal name="delete-client" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete client?</flux:heading>
                <flux:subheading>This cannot be undone. Meters attached will also be removed.</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)" x-on:click="confirmDelete = null">
                    Cancel
                </flux:button>

                @if ($deleteId)
                    <flux:button variant="danger" wire:click="delete" x-on:click="confirmDelete = null">
                        Delete
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
