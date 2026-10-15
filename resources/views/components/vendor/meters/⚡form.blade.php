<?php

use App\Models\Client;
use App\Models\Meter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.vendor')] class extends Component {
    public ?Meter $meter = null;

    public string $client_id = '';
    public string $serial = '';
    public string $location = '';
    public string $installed_at = '';
    public string $status = 'active';
    public string $billing_mode = 'postpaid';
    public string $device_id = '';
    public string $connectivity = '';

    public function mount(?Meter $meter = null): void
    {
        if ($meter && $meter->exists) {
            $this->authorize('update', $meter);
            $this->meter = $meter;
            $this->client_id = (string) $meter->client_id;
            $this->serial = $meter->serial;
            $this->location = $meter->location ?? '';
            $this->installed_at = $meter->installed_at?->format('Y-m-d') ?? '';
            $this->status = $meter->status->value;
            $this->billing_mode = $meter->billing_mode ?? 'postpaid';
            $this->device_id = (string) $meter->device_id;
            $this->connectivity = (string) $meter->connectivity;
        } else {
            $this->authorize('create', Meter::class);
            if ($clientId = request()->integer('client')) {
                $this->client_id = (string) $clientId;
            }
        }
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;
        $unique = $this->meter?->id ? 'unique:meters,serial,' . $this->meter->id : 'unique:meters,serial';

        return [
            'client_id' => ['required', "exists:clients,id,vendor_id,{$vendorId}"],
            'serial' => ['required', 'string', 'max:60', $unique],
            'location' => ['nullable', 'string', 'max:150'],
            'installed_at' => ['nullable', 'date'],
            'status' => ['required', 'in:active,faulty,removed'],
            'billing_mode' => ['required', 'in:postpaid,prepaid'],
            'device_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9:-]+$/',
                Rule::unique('meters', 'device_id')->where('vendor_id', $vendorId)->ignore($this->meter?->id)],
            'connectivity' => ['nullable', 'in:lorawan,nbiot,gprs,other'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'client_id' => $this->client_id,
            'serial' => $this->serial,
            'location' => $this->location ?: null,
            'installed_at' => $this->installed_at ?: null,
            'status' => $this->status,
            'billing_mode' => $this->billing_mode,
            'device_id' => $this->device_id ? strtolower(str_replace([':', '-'], '', trim($this->device_id))) : null,
            'connectivity' => $this->device_id ? ($this->connectivity ?: null) : null,
        ];

        if ($this->meter) {
            $this->meter->update($payload);
            session()->flash('status', 'Meter updated.');
        } else {
            Meter::create($payload);
            session()->flash('status', 'Meter created.');
        }

        $this->redirectRoute('vendor.meters.index', navigate: true);
    }

    public function render()
    {
        return view('vendor.meters.⚡form', [
            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),
        ]);
    }
};
?>

<div class="max-w-2xl">
    <form wire:submit="save" class="space-y-6">
        <div class="card">
            <div class="card-header">
                <flux:heading size="sm">{{ $meter ? 'Edit meter' : 'New meter' }}</flux:heading>
            </div>
            <div class="card-body grid gap-5 sm:grid-cols-2">

                <div class="sm:col-span-2">
                    <flux:select wire:model="client_id" label="Client" searchable>
                        <flux:select.option value="">— Select a client —</flux:select.option>
                        @foreach ($clients as $c)
                            <flux:select.option value="{{ $c->id }}">{{ $c->account_no }} · {{ $c->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input wire:model="serial" label="Serial number" class="font-mono" />

                <flux:select wire:model="status" label="Status">
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="faulty">Faulty</flux:select.option>
                    <flux:select.option value="removed">Removed</flux:select.option>
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:input wire:model="location" label="Location" placeholder="e.g. Kitchen block B, unit 4" />
                </div>

                <flux:input wire:model="installed_at" type="date" label="Installed at" />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <flux:heading size="sm">Billing &amp; smart features</flux:heading>
            </div>
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="billing_mode" label="Billing" class="sm:col-span-2"
                    description="Prepaid meters are paid for in advance: M-Pesa payments to this meter number issue an STS token instead of settling a bill.">
                    <flux:select.option value="postpaid">Postpaid — monthly bill from readings</flux:select.option>
                    <flux:select.option value="prepaid">Prepaid — STS token keypad</flux:select.option>
                </flux:select>

                <flux:input wire:model="device_id" label="Smart meter device ID (optional)" class="font-mono"
                    placeholder="DevEUI or IMEI" description="Fill in for meters that send their own readings." />

                <flux:select wire:model="connectivity" label="Connectivity">
                    <flux:select.option value="">—</flux:select.option>
                    <flux:select.option value="lorawan">LoRaWAN</flux:select.option>
                    <flux:select.option value="nbiot">NB-IoT</flux:select.option>
                    <flux:select.option value="gprs">GPRS / 4G</flux:select.option>
                    <flux:select.option value="other">Other</flux:select.option>
                </flux:select>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.meters.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $meter ? 'Save changes' : 'Create meter' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
