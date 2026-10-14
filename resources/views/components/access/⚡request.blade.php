<?php

use App\Actions\AccessRequests\SubmitAccessRequest;
use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Public page: people outside the platform ask for an account here.
 * Self-registration is disabled; an admin (or the automatic email check,
 * when enabled) approves and a signup link is emailed.
 */
new #[Layout('layouts::auth'), Title('Request access')] class extends Component {
    public string $requested_type = 'vendor';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $organization = '';
    public string $provider_name = '';
    public string $account_reference = '';
    public string $message = '';

    /** Honeypot — real people never fill this hidden field. */
    public string $website = '';

    public bool $submitted = false;
    public bool $autoApproved = false;

    protected function rules(): array
    {
        return [
            'requested_type'    => ['required', 'in:' . AccessRequest::TYPE_VENDOR . ',' . AccessRequest::TYPE_CLIENT],
            'name'              => ['required', 'string', 'max:150'],
            'email'             => ['required', 'email:rfc', 'max:150'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'organization'      => [$this->requested_type === AccessRequest::TYPE_VENDOR ? 'required' : 'nullable', 'string', 'max:150'],
            'provider_name'     => [$this->requested_type === AccessRequest::TYPE_CLIENT ? 'required' : 'nullable', 'string', 'max:150'],
            'account_reference' => ['nullable', 'string', 'max:60'],
            'message'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'organization.required'  => 'Enter the name of your water company.',
            'provider_name.required' => 'Enter the name of the company that supplies your water.',
        ];
    }

    public function submit(SubmitAccessRequest $action): void
    {
        $key = 'access-request:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many requests. Try again in ' . ceil(RateLimiter::availableIn($key) / 60) . ' minutes.');
            return;
        }
        $this->validate();

        RateLimiter::hit($key, 3600);

        // Bots fill every field — pretend success and store nothing.
        if ($this->website !== '') {
            $this->submitted = true;
            return;
        }

        $email = AccessRequest::normalizeEmail($this->email);

        // Same friendly answer whether or not the address is known, so this
        // form can't be used to discover who has an account.
        $alreadyHandled = User::where('email', $email)->exists()
            || AccessRequest::where('email', $email)
                ->whereIn('status', [AccessRequestStatus::Pending->value, AccessRequestStatus::Approved->value])
                ->exists();

        if (! $alreadyHandled) {
            $request = $action->handle($this->only([
                'requested_type', 'name', 'email', 'phone', 'organization',
                'provider_name', 'account_reference', 'message',
            ]), request()->ip());

            $this->autoApproved = $request->status === AccessRequestStatus::Approved;
        }

        $this->submitted = true;
    }
};
?>

<div class="flex flex-col gap-5">
    @if ($submitted)
        <x-auth-header :title="__('Request received')" :description="__('Thanks — we\'ve got your details.')" />

        <flux:callout variant="success" icon="envelope">
            @if ($autoApproved)
                Your request was approved. We've emailed <strong>{{ $email }}</strong> a link to create your
                account — check your inbox (and spam folder).
            @else
                An administrator will review your request. If it's approved, you'll receive a signup link at
                <strong>{{ $email }}</strong>.
            @endif
        </flux:callout>

        <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
            <flux:link :href="route('login')" wire:navigate>{{ __('Back to log in') }}</flux:link>
        </div>
    @else
        <x-auth-header :title="__('Request access')" :description="__('Approved requests get a sign-up link by email.')" />

        <form wire:submit="submit" class="flex flex-col gap-4">
            {{-- Who is asking: a compact two-option switch instead of a stacked radio list --}}
            <div role="radiogroup" aria-label="{{ __('I am') }}" class="grid grid-cols-2 gap-1 rounded-xl bg-brand-900/5 p-1 ring-1 ring-brand-900/10 dark:bg-white/10 dark:ring-white/15">
                @foreach (['vendor' => __('Water company'), 'client' => __('Water customer')] as $value => $label)
                    <button type="button" role="radio" aria-checked="{{ $requested_type === $value ? 'true' : 'false' }}"
                        wire:click="$set('requested_type', '{{ $value }}')"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ $requested_type === $value ? 'bg-brand-500 text-white shadow-sm dark:bg-brand-400 dark:text-brand-950' : 'text-brand-900/70 hover:bg-brand-900/5 hover:text-brand-950 dark:text-white/80 dark:hover:bg-white/10 dark:hover:text-white' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <flux:error name="requested_type" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Full name')" required autocomplete="name" />
                <flux:input wire:model="email" type="email" :label="__('Email address')" required autocomplete="email"
                    placeholder="email@example.com" />
                <flux:input wire:model="phone" :label="__('Phone (optional)')" autocomplete="tel" />

                @if ($requested_type === 'vendor')
                    <flux:input wire:model="organization" :label="__('Water company name')" required />
                @else
                    <flux:input wire:model="provider_name" :label="__('Your water provider')" required />
                    <div class="sm:col-span-2">
                        <flux:input wire:model="account_reference" :label="__('Account or meter number (optional)')" />
                    </div>
                @endif
            </div>

            <flux:input wire:model="message" :label="__('Anything else? (optional)')" />

            {{-- Honeypot: hidden from people, irresistible to bots --}}
            <div class="hidden" aria-hidden="true">
                <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">{{ __('Send request') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    @endif
</div>
