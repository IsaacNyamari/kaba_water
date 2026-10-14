<?php

use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.vendor', ['title' => 'Settings'])] class extends Component {
    use WithFileUploads;

    /** Wide logo for invoices; square icon for the sidebar badge and browser tab. */
    public $logo = null;
    public $icon = null;

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

        Flux::toast(variant: 'success', heading: 'Workspace updated',text:'workspace updated successfully!');
    }

    /**
     * Upload or replace the workspace logo / icon. SVG is not accepted: the
     * files are served from the public disk on this domain, and SVG can carry
     * script.
     */
    public function saveBranding(): void
    {
        $this->authorize('vendor.settings.manage');

        $this->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=2000,max_height=2000'],
        ], [
            'icon.dimensions' => 'The icon must be at least 64×64 pixels (a square image works best).',
        ]);

        if (! $this->logo && ! $this->icon) {
            $this->addError('logo', 'Choose a logo or an icon to upload.');

            return;
        }

        $vendor = auth()->user()->vendor;
        $changes = [];

        foreach (['logo' => 'logo_path', 'icon' => 'icon_path'] as $field => $column) {
            if (! $this->{$field}) {
                continue;
            }

            $changes[$column] = $this->{$field}->store("vendors/{$vendor->id}", 'public');

            if ($vendor->{$column}) {
                Storage::disk('public')->delete($vendor->{$column});
            }
        }

        $vendor->update($changes);
        $this->reset('logo', 'icon');

        // Full page load (not wire:navigate) so the browser refreshes the tab icon.
        session()->flash('status', 'Branding updated — your new logo and icon are live across your workspace.');
        $this->redirectRoute('vendor.settings.index');
    }

    public function removeBranding(string $field): void
    {
        $this->authorize('vendor.settings.manage');

        $column = ['logo' => 'logo_path', 'icon' => 'icon_path'][$field] ?? null;
        abort_unless($column, 404);

        $vendor = auth()->user()->vendor;

        if ($vendor->{$column}) {
            Storage::disk('public')->delete($vendor->{$column});
            $vendor->update([$column => null]);
        }

        session()->flash('status', ucfirst($field).' removed.');
        $this->redirectRoute('vendor.settings.index');
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
        class="app-card p-6 space-y-5">
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

    {{-- Branding --}}
    <form wire:submit="saveBranding" class="app-card p-6 space-y-5">
        <div>
            <flux:heading size="sm">Branding</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Your logo appears on invoices; your icon appears in the sidebar and the browser tab for your team
                and your clients. PNG, JPG or WEBP.
            </flux:text>
        </div>

        @php $workspace = auth()->user()->vendor; @endphp

        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ([
                ['logo', 'Logo', 'logo_path', 'Wide image, e.g. 600×200 px · max 2 MB', 'h-20 w-full object-contain'],
                ['icon', 'Icon', 'icon_path', 'Square image, at least 64×64 px · max 1 MB', 'size-20 rounded-2xl object-cover'],
            ] as [$field, $label, $column, $hint, $previewClass])
                <div wire:key="brand-{{ $field }}" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>{{ $label }}</flux:label>
                        @if ($workspace->{$column} && ! $this->{$field})
                            <flux:button size="xs" variant="ghost" icon="trash" class="!text-rose-600"
                                wire:click="removeBranding('{{ $field }}')"
                                wire:confirm="Remove the {{ strtolower($label) }}?">Remove</flux:button>
                        @endif
                    </div>

                    <label class="flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-200 bg-brand-50/50 p-4 text-center transition hover:border-brand-400 hover:bg-brand-50 dark:border-brand-500/20 dark:bg-brand-500/5 dark:hover:border-brand-400/50">
                        <input type="file" wire:model="{{ $field }}" accept="image/png,image/jpeg,image/webp" class="hidden" />

                        @if ($this->{$field})
                            <img src="{{ $this->{$field}->temporaryUrl() }}" alt="" class="{{ $previewClass }}" />
                            <span class="text-xs font-medium text-brand-700 dark:text-brand-300">New {{ strtolower($label) }} — save to apply</span>
                        @elseif ($workspace->{$column})
                            <img src="{{ \App\Support\Workspace::url($workspace->{$column}) }}" alt="" class="{{ $previewClass }}" />
                            <span class="text-xs text-zinc-500">Click to replace</span>
                        @else
                            <flux:icon :name="$field === 'logo' ? 'photo' : 'squares-2x2'" class="size-8 text-brand-500" />
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Upload {{ strtolower($label) }}</span>
                        @endif

                        <span class="text-xs text-zinc-500">{{ $hint }}</span>
                        <span wire:loading wire:target="{{ $field }}" class="text-xs font-medium text-brand-600">Uploading…</span>
                    </label>

                    <flux:error name="{{ $field }}" />
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveBranding,logo,icon">
                Save branding
            </flux:button>
        </div>
    </form>

    {{-- Your profile --}}
    <form wire:submit="saveUser"
        class="app-card p-6 space-y-5">
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
        class="app-card p-6 space-y-5">
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
