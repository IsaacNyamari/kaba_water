<?php

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Client;
use App\Models\WorkOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Work Orders')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';
    #[Url(history: true)]
    public string $status = '';
    #[Url(history: true)]
    public string $type = '';
    #[Url(history: true)]
    public string $priority = '';
    #[Url(as: 'client', history: true)]
    public ?int $clientId = null;
    #[Url(history: true)]
    public string $view = 'list'; // list|board

    public function updating(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'type', 'priority', 'clientId']);
        $this->resetPage();
    }

    public function updateStatus(int $id, string $status): void
    {
        $wo = WorkOrder::findOrFail($id);
        $this->authorize('update', $wo);

        $payload = ['status' => $status];
        if ($status === 'completed') {
            $payload['completed_at'] = now();
        }
        if ($status === 'in_progress' && !$wo->started_at) {
            $payload['started_at'] = now();
        }

        $wo->update($payload);
        session()->flash('status', "Work order {$wo->number} updated.");
    }

    public function delete(int $id): void
    {
        $wo = WorkOrder::findOrFail($id);
        $this->authorize('delete', $wo);

        $wo->delete();
        session()->flash('status', 'Work order deleted.');
    }

    public function with(): array
    {
        $query = WorkOrder::query()
            ->with(['client', 'meter', 'assignee'])
            ->when(
                $this->search,
                fn($q) => $q->where(
                    fn($q) => $q
                        ->where('number', 'like', "%{$this->search}%")
                        ->orWhere('title', 'like', "%{$this->search}%")
                        ->orWhereHas('client', fn($q) => $q->where('name', 'like', "%{$this->search}%")),
                ),
            )
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->type, fn($q) => $q->where('type', $this->type))
            ->when($this->priority, fn($q) => $q->where('priority', $this->priority))
            ->when($this->clientId, fn($q) => $q->where('client_id', $this->clientId))
            ->latest();

        return [
            'workOrders' => $query->paginate(20),
            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),
            'counts' => [
                'pending' => WorkOrder::where('status', 'pending')->count(),
                'in_progress' => WorkOrder::whereIn('status', ['assigned', 'in_progress'])->count(),
                'completed' => WorkOrder::where('status', 'completed')->count(),
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    {{-- Status KPI strip --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Pending</span>
            <div class="text-2xl font-semibold text-amber-600 mt-1">{{ $counts['pending'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">In progress</span>
            <div class="text-2xl font-semibold text-brand-600 mt-1">{{ $counts['in_progress'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Completed</span>
            <div class="text-2xl font-semibold text-accent-600 mt-1">{{ $counts['completed'] }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search number, title, client…" class="w-full sm:w-72" />

            <flux:select wire:model.live="status" class="w-full sm:w-40">
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (WorkOrderStatus::cases() as $s)
                    <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="type" class="w-full sm:w-44">
                <flux:select.option value="">All types</flux:select.option>
                @foreach (WorkOrderType::cases() as $t)
                    <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="priority" class="w-full sm:w-40">
                <flux:select.option value="">All priorities</flux:select.option>
                @foreach (WorkOrderPriority::cases() as $p)
                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($search || $status || $type || $priority || $clientId)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>

        <flux:button href="{{ route('vendor.work-orders.create') }}" variant="primary" icon="plus">
            New work order
        </flux:button>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$workOrders">
        <flux:table.columns>
            <flux:table.column>Number</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Client</flux:table.column>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Priority</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Cost</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($workOrders as $wo)
                <flux:table.row :key="$wo->id">
                    <flux:table.cell class="font-mono text-xs">
                        <a href="{{ route('vendor.work-orders.edit', $wo) }}"
                            class="text-brand-600 hover:underline">{{ $wo->number }}</a>
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        <flux:badge color="zinc" size="sm" :icon="$wo->type->icon()">
                            {{ $wo->type->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($wo->client)
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $wo->client->name }}</div>
                            <div class="text-xs text-zinc-500 font-mono">{{ $wo->client->account_no }}</div>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="max-w-xs truncate">{{ $wo->title }}</flux:table.cell>

                    <flux:table.cell class="py-0">
                        <flux:badge :color="$wo->priority->color()" size="sm">
                            {{ $wo->priority->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        <flux:badge :color="$wo->status->color()" size="sm">
                            {{ $wo->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono text-xs">
                        {{ number_format($wo->totalCost(), 2) }}
                        @if ($wo->billable)
                            <flux:badge color="violet" size="sm" class="ml-1">billable</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            @if ($wo->status->isOpen())
                                <flux:button size="sm" variant="ghost" icon="check"
                                    wire:click="updateStatus({{ $wo->id }}, 'completed')"
                                    wire:confirm="Mark complete?" title="Mark complete" />
                            @endif

                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.work-orders.edit', $wo) }}" />

                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $wo->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center py-12 text-zinc-500">
                        No work orders yet.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-wo" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete work order?</flux:heading>
                <flux:subheading>This cannot be undone.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)" x-on:click="confirmDelete = null">
                    Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
