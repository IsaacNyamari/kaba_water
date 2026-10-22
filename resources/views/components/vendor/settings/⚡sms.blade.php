<?php

use App\Models\Integration;
use App\Services\Sms\SmsService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

/*
 * Settings → SMS: the vendor's own bulk SMS account (Africa's Talking or
 * HostPinnacle) and approved sender ID, used for invoices, M-Pesa receipts
 * and prepaid tokens. Without it, messages are only logged.
 * Secrets are write-only: blank inputs keep the saved values.
 */
new class extends Component {
    public bool $enabled = false;
    public string $provider = 'africastalking';
    public string $sender_id = '';

    // Africa's Talking
    public string $username = '';
    public string $api_key = '';

    // HostPinnacle
    public string $userid = '';
    public string $password = '';
    public string $hp_api_key = '';

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
        return Integration::for(auth()->user()->vendor_id, Integration::SMS);
    }

    protected function load(): void
    {
        $i = $this->integration();
        $this->enabled = $i->is_enabled;
        $this->provider = SmsService::providerOf($i);
        $this->sender_id = (string) $i->setting('sender_id', '');
        $this->username = (string) $i->setting('username', '');
        $this->userid = (string) $i->setting('userid', '');
        $this->saved = collect(['api_key', 'password', 'hp_api_key'])->mapWithKeys(fn ($k) => [$k => filled($i->setting($k))])->all();
        $this->reset('api_key', 'password', 'hp_api_key');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $saved = fn (string $key) => $this->saved[$key] ?? false;

        $rules = [
            'provider' => ['required', Rule::in(array_keys(SmsService::PROVIDERS))],
            'sender_id' => [$this->provider === 'hostpinnacle' ? 'required' : 'nullable', 'string', 'max:11'],
        ];

        if ($this->provider === 'hostpinnacle') {
            // Either the portal user ID + password, or an API key.
            $usesKey = blank($this->userid);
            $rules += [
                'userid' => ['nullable', 'string', 'max:100'],
                'password' => [! $usesKey && ! $saved('password') ? 'required' : 'nullable', 'string', 'max:200'],
                'hp_api_key' => [$usesKey && ! $saved('hp_api_key') ? 'required' : 'nullable', 'string', 'max:300'],
            ];
        } else {
            $rules += [
                'username' => ['required', 'string', 'max:100', 'regex:/^\S+$/'],
                'api_key' => [$saved('api_key') ? 'nullable' : 'required', 'string', 'max:200'],
            ];
        }

        $this->validate($rules, [
            'username.regex' => 'The username cannot contain spaces.',
            'hp_api_key.required' => 'Enter your HostPinnacle user ID and password, or an API key.',
            'sender_id.required' => 'HostPinnacle needs your approved sender ID.',
        ], ['api_key' => 'API key', 'hp_api_key' => 'API key', 'sender_id' => 'sender ID', 'userid' => 'user ID']);

        $i = $this->integration();
        $config = array_merge($i->config ?? [], [
            'provider' => $this->provider,
            'sender_id' => trim($this->sender_id) ?: null,
            'username' => trim($this->username) ?: ($i->config['username'] ?? null),
            'userid' => $this->provider === 'hostpinnacle' ? (trim($this->userid) ?: null) : ($i->config['userid'] ?? null),
        ]);

        foreach (['api_key', 'password', 'hp_api_key'] as $secret) {
            if (filled($this->{$secret})) {
                $config[$secret] = trim($this->{$secret});
            }
        }

        $i->update(['config' => $config, 'is_enabled' => $this->enabled]);
        $this->load();

        Flux::toast(variant: 'success', heading: 'SMS settings saved', text: SmsService::PROVIDERS[$this->provider].($this->enabled ? ' is on.' : ' saved, but SMS is switched off.'));
    }

    public function sendTest(SmsService $sms): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);
        $this->validate(['test_phone' => ['required', 'string', 'max:20']], [], ['test_phone' => 'phone number']);

        $log = $sms->send(auth()->user()->vendor_id, $this->test_phone, 'Test message from '.auth()->user()->vendor->name.'. SMS is working.', 'test');

        match ($log->status) {
            'sent' => Flux::toast(variant: 'success', heading: 'Sent', text: 'Check the phone.'),
            'logged' => Flux::toast(variant: 'warning', heading: 'Only logged', text: 'SMS is switched off, so the message was recorded but not sent.'),
            default => Flux::toast(variant: 'danger', heading: 'Not sent', text: (string) $log->error),
        };
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="app-card space-y-5 p-6">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-500 text-white shadow-sm"><flux:icon.chat-bubble-left-right variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="sm">SMS</flux:heading>
                <flux:text class="text-sm">
                    Invoices, M-Pesa receipts and prepaid tokens are texted to customers from <strong>your own</strong> bulk SMS account,
                    so the cost and the sender name are yours. Pick the provider you already use.
                </flux:text>
            </div>
        </div>

        <flux:switch wire:model="enabled" label="Send SMS" description="When off, messages are recorded in the SMS log only." />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="provider" label="Provider">
                @foreach (SmsService::PROVIDERS as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="sender_id" label="Sender ID" maxlength="11"
                :description="$provider === 'hostpinnacle' ? 'Your registered and approved sender name.' : 'Optional — your approved sender name, e.g. HUMSOLS.'" />
        </div>

        @if ($provider === 'hostpinnacle')
            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">
                    Use the login of your <flux:link href="https://smsportal.hostpinnacle.co.ke" target="_blank" rel="noopener">HostPinnacle SMS portal</flux:link>,
                    Enter the user ID, API password and API key from its API settings. Most accounts need all three.
                </flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="userid" label="User ID" autocomplete="off" />
                    <flux:input type="password" wire:model="password" viewable autocomplete="off" label="Password (API password)"
                        :placeholder="($saved['password'] ?? false) ? 'Saved — leave blank to keep' : ''" />
                    <flux:input type="password" wire:model="hp_api_key" viewable autocomplete="off" label="API key" class="sm:col-span-2"
                        :placeholder="($saved['hp_api_key'] ?? false) ? 'Saved — leave blank to keep' : ''" />
                </div>
            </div>
        @else
            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">
                    From your <flux:link href="https://account.africastalking.com" target="_blank" rel="noopener">Africa's Talking</flux:link> app.
                    Use the username <span class="font-mono">sandbox</span> to test without sending real messages.
                </flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="username" label="Username" placeholder="your app username" />
                    <flux:input type="password" wire:model="api_key" viewable autocomplete="off" label="API key"
                        :placeholder="($saved['api_key'] ?? false) ? 'Saved — leave blank to keep' : ''" />
                </div>
            </div>
        @endif

        <div class="flex justify-end"><flux:button type="submit" variant="primary">Save SMS settings</flux:button></div>
    </form>

    <form wire:submit="sendTest" class="app-card space-y-4 p-6">
        <flux:heading size="sm">Send a test SMS</flux:heading>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><flux:input wire:model="test_phone" label="Phone number" placeholder="0712 345 678" /></div>
            <flux:button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="sendTest">Send test</flux:button>
        </div>
        <flux:text class="text-xs">Save your settings first. Every message sent is listed under M-Pesa payments → SMS log.</flux:text>
    </form>
</div>
