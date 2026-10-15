<?php

use App\Models\Integration;
use App\Services\Sms\SmsService;
use Flux\Flux;
use Livewire\Component;

/*
 * Settings → SMS: the vendor's own Africa's Talking account for payment
 * confirmations and prepaid tokens. Without it, messages are only logged.
 */
new class extends Component {
    public bool $enabled = false;
    public string $username = '';
    public string $api_key = '';
    public string $sender_id = '';
    public string $test_phone = '';
    public bool $hasKey = false;

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
        $this->username = (string) $i->setting('username', '');
        $this->sender_id = (string) $i->setting('sender_id', '');
        $this->hasKey = filled($i->setting('api_key'));
        $this->reset('api_key');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('vendor.settings.manage'), 403);

        $this->validate([
            'username' => ['required', 'string', 'max:100', 'regex:/^\S+$/'],
            'api_key' => [$this->hasKey ? 'nullable' : 'required', 'string', 'max:200'],
            'sender_id' => ['nullable', 'string', 'max:11'],
        ], ['username.regex' => 'The username cannot contain spaces.'], ['api_key' => 'API key', 'sender_id' => 'sender ID']);

        $i = $this->integration();
        $config = array_merge($i->config ?? [], ['username' => trim($this->username), 'sender_id' => $this->sender_id ?: null]);
        if (filled($this->api_key)) {
            $config['api_key'] = trim($this->api_key);
        }

        $i->update(['config' => $config, 'is_enabled' => $this->enabled]);
        $this->load();

        Flux::toast(variant: 'success', heading: 'SMS settings saved');
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
                <flux:heading size="sm">SMS (Africa's Talking)</flux:heading>
                <flux:text class="text-sm">
                    Sends M-Pesa receipts and prepaid tokens to customers from your own account, so the cost and sender name are yours.
                    Create an account at <flux:link href="https://account.africastalking.com" target="_blank" rel="noopener">africastalking.com</flux:link>.
                    Use the username <span class="font-mono">sandbox</span> to test without sending real messages.
                </flux:text>
            </div>
        </div>

        <flux:switch wire:model="enabled" label="Send SMS" description="When off, messages are recorded in the SMS log only." />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="username" label="Username" placeholder="your app username" />
            <flux:input wire:model="sender_id" label="Sender ID (optional)" description="Approved alphanumeric sender, e.g. HUMSOLS." maxlength="11" />
            <flux:input type="password" wire:model="api_key" viewable autocomplete="off" label="API key" class="sm:col-span-2"
                :placeholder="$hasKey ? 'Saved — leave blank to keep' : ''" />
        </div>

        <div class="flex justify-end"><flux:button type="submit" variant="primary">Save SMS settings</flux:button></div>
    </form>

    <form wire:submit="sendTest" class="app-card space-y-4 p-6">
        <flux:heading size="sm">Send a test SMS</flux:heading>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><flux:input wire:model="test_phone" label="Phone number" placeholder="0712 345 678" /></div>
            <flux:button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="sendTest">Send test</flux:button>
        </div>
        <flux:text class="text-xs">Every message sent is listed under M-Pesa payments → SMS log.</flux:text>
    </form>
</div>
