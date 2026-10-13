<?php

use App\Models\Client;
use App\Models\Meter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]  public string $search = '';
    #[Url(history: true)]           public string $status = '';
    #[Url(as: 'client', history: true)] public ?int $clientId = null;

    public function updating($field): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $meter = Meter::findOrFail($id);
        $this->authorize('delete', $meter);

        if ($meter->readings()->exists()) {
            \Flux\Flux::toast(variant: 'danger', text: 'Cannot delete a meter with readings.');
            return;
        }

        $meter->delete();
        \Flux\Flux::toast(variant: 'success', text: 'Meter deleted.');
    }

    public function render()
    {
        $meters = Meter::query()
            ->with('client')
            ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                $q->where('serial', 'like', "%{$this->search}%")
                  ->orWhere('location', 'like', "%{$this->search}%")
            ))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->latest()
            ->paginate(7);

        return view('vendor.meters.⚡index', [
            'meters'  => $meters,
            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),
        ]);
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex-1 flex flex-col sm:flex-row gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search serial or location…" class="sm:w-72" />

            <flux:select wire:model.live="clientId" class="sm:w-72">
                <flux:select.option value="">All clients</flux:select.option>
                @foreach ($clients as $c)
                    <flux:select.option value="{{ $c->id }}">
                        {{ $c->account_no }} · {{ $c->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" class="sm:w-40">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="faulty">Faulty</flux:select.option>
                <flux:select.option value="removed">Removed</flux:select.option>
            </flux:select>
        </div>

        <flux:button href="{{ route('vendor.meters.create') }}" variant="primary" icon="plus">
            Add meter
        </flux:button>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$meters">
        <flux:table.columns>
            <flux:table.column>Serial</flux:table.column>
            <flux:table.column>Client</flux:table.column>
            <flux:table.column>Location</flux:table.column>
            <flux:table.column>Installed</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($meters as $meter)
                <flux:table.row :key="$meter->id">
                    <flux:table.cell class="font-mono text-xs">
                        {{ $meter->serial }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $meter->client->name }}
                        </div>
                        <div class="text-xs text-zinc-500 font-mono">
                            {{ $meter->client->account_no }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        {{ $meter->location ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                        {{ $meter->installed_at?->format('d M Y') ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        <flux:badge :color="$meter->status->color()" size="sm">
                            {{ $meter->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="camera"
                                href="{{ route('vendor.readings.create', ['meter' => $meter->id]) }}"
                                title="Record reading" />
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.meters.edit', $meter) }}" />
                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $meter->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-10">
                        No meters found.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-meter" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete meter?</flux:heading>
                <flux:subheading>This cannot be undone.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)"
                    x-on:click="confirmDelete = null">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>