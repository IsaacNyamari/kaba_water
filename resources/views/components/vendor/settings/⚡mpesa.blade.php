<?php

use App\Models\Integration;
use App\Services\Mpesa\DarajaClient;
use App\Services\Mpesa\StkPushService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

/*
 * Settings → M-Pesa: the vendor's own Daraja app (Paybill or Till).
 * Secrets are write-only: blank inputs keep the saved values.
 */
new class extends Component {
    public bool $enabled = false;
    public string $environment = 'sandbox';
    public string $shortcode_type = 'paybill';
    public string $shortcode = '';
    public string $store_number = '';
    public string $consumer_key = '';
    public string $consumer_secret = '';
    public string $passkey = '';
    public string $test_phone = '';

    /** @var array<string, bool> which secrets are already saved */
    public array $saved = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $this->load();
    }

    protected function integration(): Integration
    {
        return Integration::for(auth()->user()->vendor_id, Integration::MPESA);
    }

    protected function load(): void
    {
        $i = $this->integration();
        $this->enabled = $i->is_enabled;
        $this->environment = (string) $i->setting('environment', 'sandbox');
        $this->shortcode_type = (string) $i->setting('shortcode_type', 'paybill');
        $this->shortcode = (string) $i->setting('shortcode', '');
        $this->store_number = (string) $i->setting('store_number', '');
        $this->saved = collect(['consumer_key', 'consumer_secret', 'passkey'])->mapWithKeys(fn ($k) => [$k => filled($i->setting($k))])->all();
        $this->reset('consumer_key', 'consumer_secret', 'passkey');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);

        $this->validate([
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'shortcode_type' => ['required', Rule::in(['paybill', 'till'])],
            'shortcode' => ['required', 'digits_between:5,8'],
            'store_number' => ['nullable', 'digits_between:5,8'],
            'consumer_key' => [$this->saved['consumer_key'] ?? false ? 'nullable' : 'required', 'string', 'max:200'],
            'consumer_secret' => [$this->saved['consumer_secret'] ?? false ? 'nullable' : 'required', 'string', 'max:200'],
            'passkey' => [$this->saved['passkey'] ?? false ? 'nullable' : 'required', 'string', 'max:200'],
        ], [], ['shortcode' => $this->shortcode_type === 'till' ? 'till number' : 'paybill number']);

        $i = $this->integration();
        $config = array_merge($i->config ?? [], [
            'environment' => $this->environment,
            'shortcode_type' => $this->shortcode_type,
            'shortcode' => $this->shortcode,
            'store_number' => $this->store_number ?: null,
        ]);

        foreach (['consumer_key', 'consumer_secret', 'passkey'] as $secret) {
            if (filled($this->{$secret})) {
                $config[$secret] = trim($this->{$secret});
            }
        }

        $i->update(['config' => $config, 'is_enabled' => $this->enabled]);
        $this->load();

        Flux::toast(variant: 'success', heading: 'M-Pesa settings saved', text: $this->enabled ? 'M-Pesa payments are on.' : 'Saved, but M-Pesa is switched off.');
    }

    public function testConnection(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);

        try {
            (new DarajaClient($this->integration()))->accessToken();
            Flux::toast(variant: 'success', heading: 'Connected', text: 'Safaricom accepted your consumer key and secret.');
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', heading: 'Connection failed', text: $e->getMessage());
        }
    }

    public function registerUrls(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $i = $this->integration();

        try {
            (new DarajaClient($i))->registerC2bUrls(
                route('webhooks.mpesa.confirmation', $i->webhook_token),
                route('webhooks.mpesa.validation', $i->webhook_token),
            );
            Flux::toast(variant: 'success', heading: 'URLs registered', text: 'Payments to your '.($this->shortcode_type === 'till' ? 'till' : 'paybill').' will now reach this system.');
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', heading: 'Safaricom refused the URLs', text: $e->getMessage());
        }
    }

    public function sendTestPrompt(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $this->validate(['test_phone' => ['required', 'string', 'max:20']], [], ['test_phone' => 'phone number']);

        $i = $this->integration();
        if (! $i->is_enabled) {
            $this->addError('test_phone', 'Switch M-Pesa on and save first.');

            return;
        }

        try {
            app(StkPushService::class)->request($i->vendor_id, $this->test_phone, '1', 'TEST', 'Test payment', ['purpose' => 'bill']);
            Flux::toast(variant: 'success', heading: 'Prompt sent', text: 'Check the phone for an M-Pesa PIN prompt of KES 1.');
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', heading: 'Prompt failed', text: $e->getMessage());
        }
    }

    public function rotateToken(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $this->integration()->rotateWebhookToken();
        Flux::toast(variant: 'warning', heading: 'Callback address changed', text: 'Register the URLs with Safaricom again, or payments will not arrive.');
    }

    public function with(): array
    {
        $i = $this->integration();

        return [
            'confirmationUrl' => route('webhooks.mpesa.confirmation', $i->webhook_token),
            'validationUrl' => route('webhooks.mpesa.validation', $i->webhook_token),
            'lastUsed' => $i->last_used_at,
        ];
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="app-card space-y-5 p-6">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm"><flux:icon.device-phone-mobile variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="sm">M-Pesa (Safaricom Daraja)</flux:heading>
                <flux:text class="text-sm">
                    Customers pay your Paybill or Till using their <strong>meter number, account number, house number or invoice number</strong> as the account.
                    Each payment clears their bill automatically, logs the M-Pesa code and sends an SMS receipt. Get your keys from
                    <flux:link href="https://developer.safaricom.co.ke" target="_blank" rel="noopener">developer.safaricom.co.ke</flux:link> (go live to use production).
                </flux:text>
            </div>
        </div>

        <flux:switch wire:model="enabled" label="Accept M-Pesa payments" description="When off, callbacks are refused and the Pay with M-Pesa buttons are hidden." />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="environment" label="Environment">
                <flux:select.option value="sandbox">Sandbox (testing)</flux:select.option>
                <flux:select.option value="production">Production (live money)</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="shortcode_type" label="Type">
                <flux:select.option value="paybill">Paybill</flux:select.option>
                <flux:select.option value="till">Buy Goods (Till)</flux:select.option>
            </flux:select>
            <flux:input wire:model="shortcode" :label="$shortcode_type === 'till' ? 'Till number' : 'Paybill number'" placeholder="e.g. 174379" />
            @if ($shortcode_type === 'till')
                <flux:input wire:model="store_number" label="Store (head office) number" description="Used to sign STK prompts on a till." />
            @else
                <div></div>
            @endif
            <flux:input type="password" wire:model="consumer_key" viewable autocomplete="off" label="Consumer key"
                :placeholder="($saved['consumer_key'] ?? false) ? 'Saved — leave blank to keep' : ''" />
            <flux:input type="password" wire:model="consumer_secret" viewable autocomplete="off" label="Consumer secret"
                :placeholder="($saved['consumer_secret'] ?? false) ? 'Saved — leave blank to keep' : ''" />
            <flux:input type="password" wire:model="passkey" viewable autocomplete="off" label="Lipa na M-Pesa passkey" class="sm:col-span-2"
                :placeholder="($saved['passkey'] ?? false) ? 'Saved — leave blank to keep' : ''" />
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <flux:button icon="signal" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection">Test keys</flux:button>
            <flux:button type="submit" variant="primary">Save M-Pesa settings</flux:button>
        </div>
    </form>

    <div class="app-card space-y-4 p-6">
        <flux:heading size="sm">Callback addresses</flux:heading>
        <flux:text class="text-sm">Safaricom sends every Paybill / Till payment here. Click <em>Register with Safaricom</em> once after saving your keys (production shortcodes can only be registered once — ask Safaricom to reset it if you change these).</flux:text>
        <div class="space-y-2 text-xs">
            <div><span class="text-zinc-500">Confirmation</span><div class="mt-0.5 break-all rounded-lg bg-zinc-50 px-3 py-2 font-mono dark:bg-zinc-800">{{ $confirmationUrl }}</div></div>
            <div><span class="text-zinc-500">Validation</span><div class="mt-0.5 break-all rounded-lg bg-zinc-50 px-3 py-2 font-mono dark:bg-zinc-800">{{ $validationUrl }}</div></div>
        </div>
        <flux:text class="text-xs">Last callback received: {{ $lastUsed?->diffForHumans() ?? 'never' }}.</flux:text>
        <div class="flex flex-wrap justify-end gap-2">
            <flux:button variant="ghost" icon="arrow-path" wire:click="rotateToken" wire:confirm="Change the secret callback address? You will need to register it with Safaricom again.">New address</flux:button>
            <flux:button variant="primary" icon="link" wire:click="registerUrls" wire:loading.attr="disabled" wire:target="registerUrls">Register with Safaricom</flux:button>
        </div>
    </div>

    <form wire:submit="sendTestPrompt" class="app-card space-y-4 p-6">
        <flux:heading size="sm">Try an STK prompt</flux:heading>
        <flux:text class="text-sm">Sends a KES 1 PIN prompt to a phone. Paying it puts KES 1 in suspense (account "TEST"), which proves the whole loop works.</flux:text>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><flux:input wire:model="test_phone" label="Phone number" placeholder="0712 345 678" /></div>
            <flux:button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="sendTestPrompt">Send prompt</flux:button>
        </div>
    </form>
</div>
