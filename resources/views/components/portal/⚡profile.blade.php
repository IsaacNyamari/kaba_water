<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.portal'), Title('Profile')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';


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

};
?>

<div class="p-4 lg:p-6 max-w-3xl space-y-6">

    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Profile</h1>
        <p class="text-sm text-zinc-500 mt-1">Manage your personal details.</p>
    </div>

    {{-- Profile --}}
    <form wire:submit="saveProfile"
        class="app-card p-6 space-y-5">
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

    {{-- Password changes live on the shared profile settings page --}}
    <div class="app-card flex flex-wrap items-center justify-between gap-3 p-6">
        <div>
            <flux:heading size="sm">Password</flux:heading>
            <flux:text class="text-sm text-zinc-500">Change your sign-in password from your account settings.</flux:text>
        </div>
        <flux:button :href="route('profile.edit')" wire:navigate icon="key">Change password</flux:button>
    </div>
</div>
