<?php

use App\Models\Integration;
use App\Models\Tariff;
use App\Services\Prepaid\TokenVendorFactory;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

/*
 * Settings → Prepaid meters: where STS tokens come from, which tariff sets
 * the units, and the share of each purchase that goes to arrears.
 */
new class extends Component {
    public bool $enabled = false;
    public string $driver = 'simulator';
    public string $tariff_id = '';
    public string $arrears_percent = '0';
    public string $url = '';
    public string $auth_header = 'Authorization';
    public string $auth_value = '';
    public string $vend_by = 'amount';
    public string $token_path = 'token';
    public string $units_path = 'units';
    public bool $hasAuth = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $this->load();
    }

    protected function integration(): Integration
    {
        return Integration::for(auth()->user()->vendor_id, Integration::TOKEN_VENDING);
    }

    protected function load(): void
    {
        $i = $this->integration();
        $this->enabled = $i->is_enabled;
        $this->driver = (string) $i->setting('driver', 'simulator');
        $this->tariff_id = (string) $i->setting('tariff_id', '');
        $this->arrears_percent = (string) $i->setting('arrears_percent', '0');
        $this->url = (string) $i->setting('http.url', '');
        $this->auth_header = (string) $i->setting('http.auth_header', 'Authorization');
        $this->vend_by = (string) $i->setting('http.vend_by', 'amount');
        $this->token_path = (string) $i->setting('http.token_path', 'token');
        $this->units_path = (string) $i->setting('http.units_path', 'units');
        $this->hasAuth = filled($i->setting('http.auth_value'));
        $this->reset('auth_value');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $vendorId = auth()->user()->vendor_id;

        $this->validate([
            'driver' => ['required', Rule::in(array_keys(TokenVendorFactory::DRIVERS))],
            'tariff_id' => ['nullable', "exists:tariffs,id,vendor_id,{$vendorId}"],
            'arrears_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'url' => ['required_if:driver,http', 'nullable', 'url:https', 'max:255'],
            'auth_header' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9-]+$/'],
            'auth_value' => ['nullable', 'string', 'max:500'],
            'vend_by' => ['required', Rule::in(['amount', 'units'])],
            'token_path' => ['nullable', 'string', 'max:80'],
            'units_path' => ['nullable', 'string', 'max:80'],
        ], ['url.required_if' => 'Enter the vending system address.', 'url.url' => 'The address must start with https://.'], ['url' => 'API address']);

        $i = $this->integration();
        $config = array_merge($i->config ?? [], [
            'driver' => $this->driver,
            'tariff_id' => $this->tariff_id ?: null,
            'arrears_percent' => (string) $this->arrears_percent,
        ]);
        $config['http'] = array_merge($config['http'] ?? [], [
            'url' => $this->url ?: null,
            'auth_header' => $this->auth_header ?: null,
            'vend_by' => $this->vend_by,
            'token_path' => $this->token_path ?: 'token',
            'units_path' => $this->units_path ?: 'units',
        ]);
        if (filled($this->auth_value)) {
            $config['http']['auth_value'] = $this->auth_value;
        }

        $i->update(['config' => $config, 'is_enabled' => $this->enabled]);
        $this->load();

        Flux::toast(variant: 'success', heading: 'Prepaid settings saved');
    }

    public function with(): array
    {
        return ['tariffs' => Tariff::orderBy('name')->get(['id', 'name', 'is_active'])];
    }
};
?>

<form wire:submit="save" class="space-y-6">
    <div class="app-card space-y-5 p-6">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-500 text-white shadow-sm"><flux:icon.key variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="sm">Prepaid meters &amp; STS tokens</flux:heading>
                <flux:text class="text-sm">
                    When a customer pays with their prepaid meter number as the M-Pesa account, a 20-digit STS token is issued and texted to them.
                    STS tokens are encrypted with keys held by an <strong>STS-certified vending system</strong> (usually your meter supplier's), so the token
                    is requested from that system — ask your supplier for their vending API and a test meter.
                </flux:text>
            </div>
        </div>

        <flux:switch wire:model="enabled" label="Sell prepaid tokens" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="driver" label="Token source">
                @foreach (TokenVendorFactory::DRIVERS as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="tariff_id" label="Tariff for units">
                <flux:select.option value="">The active tariff</flux:select.option>
                @foreach ($tariffs as $t)
                    <flux:select.option value="{{ $t->id }}">{{ $t->name }}{{ $t->is_active ? ' (active)' : '' }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="number" step="0.01" min="0" max="100" wire:model="arrears_percent" label="Share of each purchase for arrears (%)"
                description="E.g. 25: a KES 1,000 payment by a customer with unpaid bills puts KES 250 on those bills and KES 750 into water. 0 turns it off." class="sm:col-span-2" />
        </div>

        @if ($driver === 'simulator')
            <div class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <flux:icon.exclamation-triangle variant="mini" class="mt-0.5 size-5 shrink-0" />
                Test mode issues random numbers so you can try the M-Pesa → token → SMS flow. They will not load on a real meter.
            </div>
        @endif
    </div>

    @if ($driver === 'http')
        <div class="app-card space-y-5 p-6">
            <div>
                <flux:heading size="sm">Vending system web API</flux:heading>
                <flux:text class="text-sm">
                    We send <span class="font-mono">POST</span> with JSON
                    <span class="font-mono text-xs">{"meter_number", "amount", "units", "reference", "vend_by"}</span>
                    and read the 20-digit token from the response.
                </flux:text>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="url" label="API address" placeholder="https://vending.example.com/api/vend" class="sm:col-span-2" />
                <flux:input wire:model="auth_header" label="Auth header name" placeholder="Authorization" />
                <flux:input type="password" wire:model="auth_value" viewable autocomplete="off" label="Auth header value"
                    :placeholder="$hasAuth ? 'Saved — leave blank to keep' : 'Bearer …'" />
                <flux:select wire:model="vend_by" label="Vend by">
                    <flux:select.option value="amount">Amount (the system applies its tariff)</flux:select.option>
                    <flux:select.option value="units">Units (we work out m³ from our tariff)</flux:select.option>
                </flux:select>
                <div class="grid grid-cols-2 gap-2">
                    <flux:input wire:model="token_path" label="Token field" placeholder="token" />
                    <flux:input wire:model="units_path" label="Units field" placeholder="units" />
                </div>
            </div>
        </div>
    @endif

    <div class="flex justify-end"><flux:button type="submit" variant="primary">Save prepaid settings</flux:button></div>
</form>
