<?php

use App\Enums\WorkOrderStatus;
use App\Livewire\Concerns\InteractsWithClient;
use App\Models\WorkOrder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Client portal — work orders raised against the signed-in client's account.
 */
new #[Layout('layouts.portal'), Title('Work Orders')] class extends Component {
    use InteractsWithClient, WithPagination;

    #[Url(history: true)]
    public string $status = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    public function with(): array
    {
        return [
            'workOrders' => WorkOrder::query()
                ->with('meter')
                ->where('client_id', $this->client->id)
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->latest()
                ->paginate(15),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <flux:heading size="lg">Work orders</flux:heading>

        <flux:select wire:model.live="status" class="sm:w-48">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (WorkOrderStatus::cases() as $s)
                <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$workOrders">
        <flux:table.columns>
            <flux:table.column>Number</flux:table.column>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Meter</flux:table.column>
            <flux:table.column>Scheduled</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($workOrders as $wo)
                <flux:table.row :key="$wo->id">
                    <flux:table.cell class="font-mono text-xs">{{ $wo->number ?? '—' }}</flux:table.cell>
                    <flux:table.cell variant="strong">
                        {{ $wo->title }}
                        @if ($wo->status === WorkOrderStatus::Completed && $wo->resolution)
                            <div class="text-xs font-normal text-zinc-500 mt-1">{{ $wo->resolution }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $wo->type->label() }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $wo->meter?->serial ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $wo->scheduled_at?->format('d M Y H:i') ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge :color="$wo->status->color()" size="sm">{{ $wo->status->label() }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-10">
                        No work orders on your account.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
