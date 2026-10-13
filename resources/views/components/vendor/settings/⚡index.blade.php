<?php

use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.vendor', ['title' => 'Settings'])] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $currency = 'KES';

    public string $user_name = '';
    public string $user_email = '';
    public string $current_password = '';
    public string $new_password = '';

    public function mount(): void
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor, 403);

        $this->name = $vendor->name;
        $this->email = $vendor->email;
        $this->phone = $vendor->phone ?? '';
        $this->currency = $vendor->currency;

        $this->user_name = auth()->user()->name;
        $this->user_email = auth()->user()->email;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('vendors', 'email')->ignore(auth()->user()->vendor_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'currency' => ['required', 'string', 'size:3'],

            'user_name' => ['required', 'string', 'max:150'],
            'user_email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore(auth()->id())],
        ];
    }

    public function saveVendor(): void
    {
        $this->authorize('vendor.settings.manage');

        $this->validateOnly('name');
        $this->validateOnly('email');
        $this->validateOnly('phone');
        $this->validateOnly('currency');

        auth()
            ->user()
            ->vendor->update([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone ?: null,
                'currency' => strtoupper($this->currency),
            ]);

        Flux::toast(variant: 'success', heading: 'Workspace updated');
    }

    public function saveUser(): void
    {
        $this->validateOnly('user_name');
        $this->validateOnly('user_email');

        auth()
            ->user()
            ->update([
                'name' => $this->user_name,
                'email' => $this->user_email,
            ]);

        Flux::toast(variant: 'success', heading: 'Profile updated',text:'profile updated successfully!');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'string', \Illuminate\Validation\Rules\Password::default()],
        ]);

        if (!Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'Current password is incorrect.');
            return;
        }

        auth()
            ->user()
            ->update(['password' => $this->new_password]);

        $this->reset('current_password', 'new_password');
        Flux::toast(variant: 'success', heading: 'Password updated');
    }
};
?>
<div class="p-4 lg:p-6 max-w-3xl space-y-6">

    {{-- Workspace --}}
    <form wire:submit="saveVendor"
        class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-5">
        <div>
            <flux:heading size="sm">Workspace</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                These details appear on every invoice you send to clients.
            </flux:text>
        </div>

        <flux:input wire:model="name" label="Business name" />
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="email" type="email" label="Business email" />
            <flux:input wire:model="phone" label="Business phone" />
        </div>
        <flux:input wire:model="currency" label="Currency" description="3-letter ISO code, e.g. KES, USD, TZS."
            maxlength="3" class="uppercase" />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save workspace</flux:button>
        </div>
    </form>

    {{-- Your profile --}}
    <form wire:submit="saveUser"
        class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-5">
        <div>
            <flux:heading size="sm">Your profile</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Personal account used to sign in to this workspace.
            </flux:text>
        </div>

        <flux:input wire:model="user_name" label="Your name" />
        <flux:input wire:model="user_email" type="email" label="Email" />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save profile</flux:button>
        </div>
    </form>

    {{-- Password --}}
    <form wire:submit="updatePassword"
        class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-5">
        <div>
            <flux:heading size="sm">Password</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Use at least 8 characters.
            </flux:text>
        </div>

        <flux:input wire:model="current_password" type="password" label="Current password" />
        <flux:input wire:model="new_password" type="password" label="New password" />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Update password</flux:button>
        </div>
    </form>
</div>
