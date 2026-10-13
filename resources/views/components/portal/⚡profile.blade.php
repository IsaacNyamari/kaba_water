<?php

use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.portal'), Title('Profile')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';

    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $client = $user->client;

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $client?->phone ?? '';
        $this->address = $client?->address ?? '';
    }

    public function saveProfile(): void
    {
        $user = auth()->user();

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        if ($user->client) {
            $user->client->update([
                'phone' => $this->phone ?: null,
                'address' => $this->address ?: null,
            ]);
        }

        \Flux::toast(variant: 'success', heading: 'Profile updated');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'Current password is incorrect.');
            return;
        }

        auth()
            ->user()
            ->update(['password' => $this->new_password]);

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        \Flux::toast(variant: 'success', heading: 'Password updated');
    }
};
?>

<div class="p-4 lg:p-6 max-w-3xl space-y-6">

    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Profile</h1>
        <p class="text-sm text-zinc-500 mt-1">Manage your personal details and password.</p>
    </div>

    {{-- Profile --}}
    <form wire:submit="saveProfile"
        class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-5">
        <flux:heading size="sm">Your details</flux:heading>

        <flux:input wire:model="name" label="Full name" />
        <flux:input wire:model="email" type="email" label="Email" />

        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="phone" label="Phone" />
        </div>

        <flux:textarea wire:model="address" label="Address" rows="2" />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save changes</flux:button>
        </div>
    </form>

    {{-- Password --}}
    <form wire:submit="updatePassword"
        class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-5">
        <div>
            <flux:heading size="sm">Password</flux:heading>
            <flux:text class="text-sm text-zinc-500">Use at least 8 characters.</flux:text>
        </div>

        <flux:input wire:model="current_password" type="password" label="Current password" />
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="new_password" type="password" label="New password" />
            <flux:input wire:model="new_password_confirmation" type="password" label="Confirm new password" />
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Update password</flux:button>
        </div>
    </form>
</div>
