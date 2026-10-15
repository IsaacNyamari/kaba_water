<?php

use App\Models\Integration;
use Flux\Flux;
use Livewire\Component;

/*
 * Settings → Smart meters: the webhook address a LoRaWAN network server
 * (ChirpStack, The Things Stack) or a meter supplier's system posts
 * readings to, and how to read the value.
 */
new class extends Component {
    public bool $enabled = false;
    public bool $auto_approve = false;
    public string $value_field = '';
    public string $value_divisor = '1';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $i = $this->integration();
        $this->enabled = $i->is_enabled;
        $this->auto_approve = (bool) $i->setting('auto_approve', false);
        $this->value_field = (string) $i->setting('value_field', '');
        $this->value_divisor = (string) $i->setting('value_divisor', '1');
    }

    protected function integration(): Integration
    {
        return Integration::for(auth()->user()->vendor_id, Integration::SMART_METERS);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);

        $this->validate([
            'value_field' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_.]+$/'],
            'value_divisor' => ['required', 'numeric', 'gt:0'],
        ], ['value_field.regex' => 'Use letters, digits, underscores and dots only.']);

        $i = $this->integration();
        $i->update([
            'is_enabled' => $this->enabled,
            'config' => array_merge($i->config ?? [], [
                'auto_approve' => $this->auto_approve,
                'value_field' => $this->value_field ?: null,
                'value_divisor' => (string) $this->value_divisor,
            ]),
        ]);

        Flux::toast(variant: 'success', heading: 'Smart meter settings saved');
    }

    public function rotateToken(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $this->integration()->rotateWebhookToken();
        Flux::toast(variant: 'warning', heading: 'Webhook address changed', text: 'Update it in your network server or supplier portal.');
    }

    public function with(): array
    {
        $i = $this->integration();

        return ['webhookUrl' => route('webhooks.meters', $i->webhook_token), 'lastUsed' => $i->last_used_at];
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="app-card space-y-5 p-6">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-500 text-white shadow-sm"><flux:icon.signal variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="sm">Smart meters (LoRaWAN · NB-IoT · GPRS)</flux:heading>
                <flux:text class="text-sm">
                    Smart meters send their readings to a network server or the supplier's head-end system, which forwards them here.
                    Link a meter by entering its <strong>device ID</strong> (DevEUI or IMEI) on the meter. Values arrive every few hours; on the 1st of each month
                    the last value becomes the billing reading, and alerts such as leaks, tampering or low battery notify your team immediately.
                </flux:text>
            </div>
        </div>

        <flux:switch wire:model="enabled" label="Receive smart meter data" />
        <flux:switch wire:model="auto_approve" label="Approve monthly smart readings automatically" description="Off: they wait in Readings for someone to check before billing." />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="value_field" label="Reading field (optional)" placeholder="e.g. totalVolume"
                description="Name of the meter-reading field in the decoded data. Blank tries reading, volume, total_volume, totalVolume, index, value." />
            <flux:input type="number" step="any" wire:model="value_divisor" label="Divide by"
                description="1 if the meter reports m³; 1000 if it reports litres." />
        </div>

        <div class="flex justify-end"><flux:button type="submit" variant="primary">Save smart meter settings</flux:button></div>
    </form>

    <div class="app-card space-y-4 p-6">
        <flux:heading size="sm">Webhook address</flux:heading>
        <div class="break-all rounded-lg bg-zinc-50 px-3 py-2 font-mono text-xs dark:bg-zinc-800">{{ $webhookUrl }}</div>
        <flux:text class="text-xs">Last data received: {{ $lastUsed?->diffForHumans() ?? 'never' }}. Keep this address secret — anyone with it can post readings.</flux:text>

        <div class="grid gap-4 text-sm lg:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="font-semibold">ChirpStack v4</p>
                <p class="mt-1 text-xs text-zinc-500">Application → Integrations → HTTP. Payload encoding JSON, event endpoint = the address above. Add a codec that decodes the meter's payload.</p>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="font-semibold">The Things Stack</p>
                <p class="mt-1 text-xs text-zinc-500">Application → Integrations → Webhooks → Custom. Base URL = the address above, enable "Uplink message", JSON format.</p>
            </div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="font-semibold">Supplier / head-end system</p>
                <p class="mt-1 text-xs text-zinc-500">POST JSON:</p>
                <pre class="mt-1 overflow-x-auto rounded bg-zinc-50 p-2 text-[11px] dark:bg-zinc-800">{"readings":[{"device_id":"70b3d5…",
 "value":123.456,
 "read_at":"2026-10-15T08:00:00Z",
 "battery":87,"alerts":["leak"]}]}</pre>
            </div>
        </div>

        <div class="flex justify-end">
            <flux:button variant="ghost" icon="arrow-path" wire:click="rotateToken" wire:confirm="Change the webhook address? Meters keep posting to the old one until you update it.">New address</flux:button>
        </div>
    </div>
</div>
