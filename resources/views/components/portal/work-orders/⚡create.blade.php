<?php

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Meter;
use App\Models\WorkOrder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Client portal — raise a work order (repair, leak, reconnection…). The
 * vendor's team is notified straight away and assigns someone to it.
 */
new #[Layout('layouts.portal'), Title('Request a repair')] class extends Component {
    use InteractsWithClient;

    /** Request types a customer can raise (installations etc. are the vendor's call). */
    public const CLIENT_TYPES = ['repair', 'maintenance', 'inspection', 'reconnection', 'disconnection', 'other'];

    public string $meter_id = '';
    public string $type = 'repair';
    public string $priority = 'normal';
    public string $title = '';
    public string $description = '';

    public function mount(): void
    {
        $this->authorize('client.work_orders.create');

        if ($this->meters->count() === 1) {
            $this->meter_id = (string) $this->meters->first()->id;
        }
    }

    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    #[Computed]
    public function meters()
    {
        return Meter::where('client_id', $this->client->id)->orderBy('serial')->get(['id', 'serial', 'location']);
    }

    protected function rules(): array
    {
        return [
            'meter_id' => ['nullable', Rule::exists('meters', 'id')->where('client_id', $this->client->id)],
            'type' => ['required', Rule::in(self::CLIENT_TYPES)],
            'priority' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function save(): void
    {
        $this->authorize('client.work_orders.create');
        $this->validate();

        $client = $this->client;

        // The observer notifies the vendor's team (database + live via Reverb).
        $workOrder = WorkOrder::create([
            'vendor_id' => $client->vendor_id,
            'client_id' => $client->id,
            'meter_id' => $this->meter_id ?: null,
            'number' => WorkOrder::nextNumber($client->vendor_id),
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => WorkOrderStatus::Pending,
            'title' => $this->title,
            'description' => $this->description ?: null,
        ]);

        session()->flash('status', "Request {$workOrder->number} sent. You'll be notified when someone is assigned.");
        $this->redirectRoute('portal.work-orders.index', navigate: true);
    }
};
?>

<div class="p-4 lg:p-6 max-w-3xl space-y-6">
    <div class="stat-card tone-brand p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-sm">
                <flux:icon.wrench-screwdriver variant="mini" class="size-5" />
            </span>
            <div>
                <flux:heading size="lg">Request a repair or service</flux:heading>
                <flux:text class="text-sm">Tell us what's wrong. Your water provider is notified straight away and will assign someone to it.</flux:text>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="app-card space-y-6 p-6">
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:select wire:model="type" label="What do you need?">
                @foreach (self::CLIENT_TYPES as $value)
                    <flux:select.option value="{{ $value }}">{{ WorkOrderType::from($value)->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="meter_id" label="Which meter?" placeholder="Not about a specific meter">
                <flux:select.option value="">Not about a specific meter</flux:select.option>
                @foreach ($this->meters as $meter)
                    <flux:select.option value="{{ $meter->id }}">{{ $meter->serial }}{{ $meter->location ? ' · '.$meter->location : '' }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:input wire:model="title" label="Short summary" placeholder="e.g. Leaking pipe next to the meter" />

        <flux:textarea wire:model="description" label="Details (optional)" rows="4"
            placeholder="Where is it, when did it start, anything we should know to find it?" />

        <flux:radio.group wire:model="priority" label="How urgent is it?">
            <flux:radio value="normal" label="Normal" description="Can wait for the next available slot." />
            <flux:radio value="high" label="Soon" description="Causing a problem; please come this week." />
            <flux:radio value="urgent" label="Urgent" description="Burst pipe, no water, or water damage." />
        </flux:radio.group>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" :href="route('portal.work-orders.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="save">Send request</flux:button>
        </div>
    </form>
</div>
