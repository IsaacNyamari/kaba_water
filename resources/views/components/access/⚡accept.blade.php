<?php

use App\Actions\AccessRequests\AcceptInvitation;
use App\Models\AccessRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Opened from the emailed (signed, expiring, single-use) signup link.
 * The signature is checked on the first page load; the token is kept in a
 * locked property and re-checked against the database when submitting.
 */
new #[Layout('layouts::auth'), Title('Create your account')] class extends Component {
    #[Locked]
    public ?int $requestId = null;

    #[Locked]
    public string $token = '';

    #[Locked]
    public string $email = '';

    #[Locked]
    public bool $valid = false;

    public string $name = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(AccessRequest $accessRequest): void
    {
        $this->token = (string) request()->query('token', '');

        $this->valid = request()->hasValidSignature()
            && $accessRequest->inviteIsUsable()
            && $accessRequest->tokenMatches($this->token);

        if ($this->valid) {
            $this->requestId = $accessRequest->id;
            $this->email = $accessRequest->email;
            $this->name = $accessRequest->name;
        }
    }

    public function register(AcceptInvitation $action): void
    {
        abort_unless($this->valid && $this->requestId, 403);

        $this->validate([
            'name'     => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ]);

        $user = $action->handle(AccessRequest::findOrFail($this->requestId), $this->token, $this->name, $this->password);

        Auth::login($user);
        session()->regenerate();

        $this->redirect($user->homeRoute());
    }
};
?>

<div class="flex flex-col gap-6">
    @if (! $valid)
        <x-auth-header :title="__('Link expired')" :description="__('This signup link is invalid, expired or already used.')" />

        <flux:callout variant="warning" icon="exclamation-triangle">
            Signup links work once and expire after {{ config('access.invite_expires_days') }} days.
            If you still need access, send a new request or ask the administrator to resend your link.
        </flux:callout>

        <div class="flex flex-col gap-2 text-sm text-center">
            <flux:link :href="route('access.request')" wire:navigate>{{ __('Request access again') }}</flux:link>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    @else
        <x-auth-header :title="__('Create your account')" :description="__('Your access was approved — choose a password to finish.')" />

        <form wire:submit="register" class="flex flex-col gap-5">
            <flux:input :value="$email" :label="__('Email address')" type="email" disabled />
            <flux:input wire:model="name" :label="__('Full name')" required autocomplete="name" />
            <flux:input wire:model="password" :label="__('Password')" type="password" required
                autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required
                autocomplete="new-password" viewable />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">{{ __('Create account') }}</span>
                <span wire:loading wire:target="register">{{ __('Creating…') }}</span>
            </flux:button>
        </form>
    @endif
</div>
