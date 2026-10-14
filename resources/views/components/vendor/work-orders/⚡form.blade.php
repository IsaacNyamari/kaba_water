<?php

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Client;
use App\Models\Meter;
use App\Models\User;
use App\Models\WorkOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor')] class extends Component
{
    public ?WorkOrder $workOrder = null;

    public string $client_id     = '';
    public string $meter_id      = '';
    public string $assigned_to   = '';
    public string $type          = 'repair';
    public string $priority      = 'normal';
    public string $status        = 'pending';
    public string $title         = '';
    public string $description   = '';
    public string $resolution    = '';
    public string $scheduled_at  = '';
    public string $materials_cost = '0';
    public string $labor_cost     = '0';
    public bool   $billable      = false;

    public function mount(?WorkOrder $workOrder = null): void
    {
        if ($workOrder && $workOrder->exists) {
            $this->authorize('update', $workOrder);
            $this->workOrder = $workOrder;
            $this->client_id      = (string) ($workOrder->client_id ?? '');
            $this->meter_id       = (string) ($workOrder->meter_id ?? '');
            $this->assigned_to    = (string) ($workOrder->assigned_to ?? '');
            $this->type           = $workOrder->type->value;
            $this->priority       = $workOrder->priority->value;
            $this->status         = $workOrder->status->value;
            $this->title          = $workOrder->title;
            $this->description    = $workOrder->description ?? '';
            $this->resolution     = $workOrder->resolution ?? '';
            $this->scheduled_at   = $workOrder->scheduled_at?->format('Y-m-d\TH:i') ?? '';
            $this->materials_cost = (string) $workOrder->materials_cost;
            $this->labor_cost     = (string) $workOrder->labor_cost;
            $this->billable       = $workOrder->billable;
        } else {
            $this->authorize('create', WorkOrder::class);
        }
    }

    public function title(): string
    {
        return $this->workOrder ? 'Edit work order' : 'New work order';
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        return [
            'client_id'      => ['nullable', "exists:clients,id,vendor_id,{$vendorId}"],
            'meter_id'       => ['nullable', "exists:meters,id,vendor_id,{$vendorId}"],
            'assigned_to'    => ['nullable', "exists:users,id,vendor_id,{$vendorId}"],
            'type'           => ['required', 'in:' . collect(WorkOrderType::cases())->pluck('value')->implode(',')],
            'priority'       => ['required', 'in:' . collect(WorkOrderPriority::cases())->pluck('value')->implode(',')],
            'status'         => ['required', 'in:' . collect(WorkOrderStatus::cases())->pluck('value')->implode(',')],
            'title'          => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'resolution'     => ['nullable', 'string', 'max:2000'],
            'scheduled_at'   => ['nullable', 'date'],
            'materials_cost' => ['required', 'numeric', 'min:0'],
            'labor_cost'     => ['required', 'numeric', 'min:0'],
            'billable'       => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->authorize($this->workOrder ? 'update' : 'create', $this->workOrder ?? WorkOrder::class);
        $this->validate();

        $payload = [
            'client_id'      => $this->client_id ?: null,
            'meter_id'       => $this->meter_id ?: null,
            'assigned_to'    => $this->assigned_to ?: null,
            'type'           => $this->type,
            'priority'       => $this->priority,
            // Giving a pending job to someone moves it to "assigned".
            'status'         => $this->assigned_to && $this->status === 'pending' ? 'assigned' : $this->status,
            'title'          => $this->title,
            'description'    => $this->description ?: null,
            'resolution'     => $this->resolution ?: null,
            'scheduled_at'   => $this->scheduled_at ?: null,
            'materials_cost' => $this->materials_cost,
            'labor_cost'     => $this->labor_cost,
            'billable'       => $this->billable,
        ];

        if ($this->status === 'completed' && ! $this->workOrder?->completed_at) {
            $payload['completed_at'] = now();
        } elseif ($this->status !== 'completed') {
            $payload['completed_at'] = null;
        }
        if ($this->status === 'in_progress' && ! $this->workOrder?->started_at) {
            $payload['started_at'] = now();
        }

        if ($this->workOrder) {
            $this->workOrder->update($payload);
            session()->flash('status', 'Work order updated.');
        } else {
            $payload['number'] = WorkOrder::nextNumber(auth()->user()->vendor_id);
            WorkOrder::create($payload);

            session()->flash('status', 'Work order created.');
        }

        $this->redirectRoute('vendor.work-orders.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),
            'meters'  => $this->client_id
                ? Meter::where('client_id', $this->client_id)->get(['id', 'serial'])
                : collect(),
            // Anyone on the team who can be sent on a job: technicians first, then office staff and owners.
            'staff'   => User::where('vendor_id', auth()->user()->vendor_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['technician', 'vendor_staff', 'vendor']))
                ->with('roles')
                ->get(['id', 'name'])
                ->sortBy(fn ($u) => [$u->hasRole('technician') ? 0 : 1, $u->name])
                ->values(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 max-w-3xl space-y-6">

    <form wire:submit="save" class="space-y-6">

        {{-- What & where --}}
        <flux:card class="space-y-5">
            <flux:heading size="sm">{{ $workOrder ? 'Edit work order' : 'New work order' }}</flux:heading>

            <flux:input wire:model="title" label="Title"
                placeholder="e.g. Leaking meter valve" />

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:select wire:model="type" label="Type">
                    @foreach (WorkOrderType::cases() as $t)
                        <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="priority" label="Priority">
                    @foreach (WorkOrderPriority::cases() as $p)
                        <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="status" label="Status">
                    @foreach (WorkOrderStatus::cases() as $s)
                        <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:select wire:model.live="client_id" label="Client" searchable placeholder="— Optional —">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($clients as $c)
                        <flux:select.option value="{{ $c->id }}">
                            {{ $c->account_no }} · {{ $c->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="meter_id" label="Meter" searchable placeholder="— Optional —">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($meters as $m)
                        <flux:select.option value="{{ $m->id }}">{{ $m->serial }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:select wire:model="assigned_to" label="Assign to" searchable placeholder="— Unassigned —">
                <flux:select.option value="">— Unassigned —</flux:select.option>
                @foreach ($staff as $s)
                    <flux:select.option value="{{ $s->id }}">{{ $s->name }} · {{ \App\Support\TeamRoles::label($s->roles->pluck('name')->first(fn ($r) => in_array($r, ['technician', 'vendor_staff', 'vendor'], true))) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="datetime-local" wire:model="scheduled_at" label="Scheduled for" />
        </flux:card>

        {{-- Details --}}
        <flux:card class="space-y-5">
            <flux:heading size="sm">Details</flux:heading>

            <flux:textarea wire:model="description" label="Description"
                placeholder="What needs to be done?" rows="3" />

            <flux:textarea wire:model="resolution" label="Resolution notes"
                placeholder="What was done? (fill when completed)" rows="3" />
        </flux:card>

        {{-- Costing --}}
        <flux:card class="space-y-5">
            <flux:heading size="sm">Costing & billing</flux:heading>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model.live="materials_cost" type="number" step="0.01" min="0"
                    label="Materials cost" />
                <flux:input wire:model.live="labor_cost" type="number" step="0.01" min="0"
                    label="Labor cost" />
            </div>

            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4 text-sm flex items-center justify-between">
                <span class="text-zinc-600 dark:text-zinc-400">Total cost</span>
                <span class="font-mono font-semibold">
                    {{ number_format((float) $materials_cost + (float) $labor_cost, 2) }}
                </span>
            </div>

            <flux:checkbox wire:model="billable"
                label="Bill this work to the client"
                description="Charge the client for this work via an invoice." />
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.work-orders.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $workOrder ? 'Save changes' : 'Create work order' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>