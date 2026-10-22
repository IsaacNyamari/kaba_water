<?php

use App\Services\GeminiOcrService;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.vendor', ['title' => 'Settings'])] class extends Component {
    use WithFileUploads;

    /** Open section: workspace | branding | mpesa | sms | prepaid | smart-meters | integrations | profile. Passwords are changed on profile.edit. */
    #[Url(as: 'tab', history: true)]
    public string $tab = 'workspace';

    /** Wide logo for invoices; square icon for the sidebar badge and browser tab. */
    public $logo = null;
    public $icon = null;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $currency = 'KES';

    /** water | property — see App\Enums\BusinessType. */
    public string $business_type = 'water';

    /** New Gemini key input only; the saved key is never put in component state. */
    public string $gemini_key = '';

    /** Comma-separated Gemini models, tried in order. Blank uses the platform default. */
    public string $gemini_models = '';

    public string $user_name = '';
    public string $user_email = '';

    public function mount(): void
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor, 403);

        $this->name = $vendor->name;
        $this->email = $vendor->email;
        $this->phone = $vendor->phone ?? '';
        $this->currency = $vendor->currency;
        $this->business_type = $vendor->business_type->value;
        $this->gemini_models = (string) $vendor->gemini_models;

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
            'business_type' => ['required', Rule::enum(\App\Enums\BusinessType::class)],

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
        $this->validateOnly('business_type');

        auth()
            ->user()
            ->vendor->update([
                'name' => $this->name,
                'business_type' => $this->business_type,
                'email' => $this->email,
                'phone' => $this->phone ?: null,
                'currency' => strtoupper($this->currency),
            ]);

        Flux::toast(variant: 'success', heading: 'Workspace updated',text:'workspace updated successfully!');

        // The menu (Houses, Tenants…) follows the business type, so reload it.
        if (auth()->user()->vendor->wasChanged('business_type')) {
            $this->redirectRoute('vendor.settings.index', navigate: false);
        }
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
        $this->redirectRoute('vendor.settings.index', ['tab' => 'branding']);
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
        $this->redirectRoute('vendor.settings.index', ['tab' => 'branding']);
    }

    /**
     * Save the vendor's own Gemini API key. It is checked with Gemini first,
     * stored encrypted, and never sent back to the browser.
     */
    public function saveGeminiKey(GeminiOcrService $ocr): void
    {
        $this->authorize('vendor.settings.manage');

        $this->validate(
            ['gemini_key' => ['required', 'string', 'max:255', 'regex:/^\S+$/']],
            ['gemini_key.regex' => 'The key cannot contain spaces.'],
            ['gemini_key' => 'Gemini API key'],
        );

        if ($problem = $ocr->verifyKey($this->gemini_key)) {
            $this->addError('gemini_key', $problem);

            return;
        }

        auth()->user()->vendor->update(['gemini_api_key' => $this->gemini_key]);
        $this->reset('gemini_key');

        session()->flash('status', 'Gemini API key saved. Meter photos are now read with your own key.');
        $this->redirectRoute('vendor.settings.index', ['tab' => 'integrations']);
    }

    /** Check the saved key still works. */
    public function testGeminiKey(GeminiOcrService $ocr): void
    {
        $this->authorize('vendor.settings.manage');

        $key = auth()->user()->vendor->gemini_api_key;

        if (blank($key)) {
            Flux::toast(variant: 'warning', heading: 'No key saved');

            return;
        }

        $problem = $ocr->verifyKey($key);

        $problem
            ? Flux::toast(variant: 'danger', heading: 'Key check failed', text: $problem)
            : Flux::toast(variant: 'success', heading: 'Key works', text: 'Gemini accepted your API key.');
    }

    /** Save which Gemini models are tried for meter photos, in order. */
    public function saveGeminiModels(): void
    {
        $this->authorize('vendor.settings.manage');

        $this->validate(
            ['gemini_models' => ['nullable', 'string', 'max:255', 'regex:/^\s*[a-z0-9][a-z0-9.\-]*(\s*,\s*[a-z0-9][a-z0-9.\-]*)*\s*$/']],
            ['gemini_models.regex' => 'Use model names separated by commas, e.g. gemini-flash-lite-latest, gemini-flash-latest.'],
            ['gemini_models' => 'Gemini models'],
        );

        $models = GeminiOcrService::parseModels($this->gemini_models);
        auth()->user()->vendor->update(['gemini_models' => $models ? implode(',', $models) : null]);
        $this->gemini_models = implode(',', $models);

        Flux::toast(variant: 'success', heading: 'Models saved', text: $models ? 'Tried in order: '.implode(' → ', $models).'.' : 'Using the platform default models.');
    }

    public function removeGeminiKey(): void
    {
        $this->authorize('vendor.settings.manage');

        auth()->user()->vendor->update(['gemini_api_key' => null]);

        session()->flash('status', 'Gemini API key removed.');
        $this->redirectRoute('vendor.settings.index', ['tab' => 'integrations']);
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

};
?>
@php
    $sections = [
        'workspace' => ['Workspace', 'building-storefront'],
        'branding' => ['Branding', 'swatch'],
        'mpesa' => ['M-Pesa', 'device-phone-mobile'],
        'sms' => ['SMS', 'chat-bubble-left-right'],
        'prepaid' => ['Prepaid meters', 'key'],
        'smart-meters' => ['Smart meters', 'signal'],
        'integrations' => ['Gemini AI', 'puzzle-piece'],
        'profile' => ['Your profile', 'user-circle'],
    ];
    $workspace = auth()->user()->vendor;
@endphp

<div class="p-4 lg:p-6 space-y-6">
    {{-- Header --}}
    <div class="stat-card tone-brand p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center overflow-hidden rounded-xl bg-brand-500 text-white shadow-sm">
                @include('components.partials.workspace-mark', ['brand' => \App\Support\Workspace::current()])
            </span>
            <div>
                <flux:heading size="lg">{{ $workspace->name }} settings</flux:heading>
                <flux:text class="text-sm">Your business details, branding, payments, SMS and meters. Changes apply to your whole workspace.</flux:text>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        {{-- Section menu --}}
        <nav class="flex gap-2 overflow-x-auto lg:flex-col lg:overflow-visible" aria-label="Settings sections">
            @foreach ($sections as $key => [$title, $icon])
                <button type="button" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}"
                    class="flex shrink-0 items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-left text-sm font-medium transition
                        {{ $tab === $key ? 'bg-brand-500 text-white shadow-sm' : 'text-zinc-600 hover:bg-brand-50 hover:text-brand-700 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300' }}">
                    <flux:icon :name="$icon" variant="mini" class="size-4" />
                    {{ $title }}
                </button>
            @endforeach
        </nav>

        {{-- Section body --}}
        <div wire:key="panel-{{ $tab }}">
            @if ($tab === 'branding')
                <form wire:submit="saveBranding" class="app-card space-y-6 p-6">
                    <div>
                        <flux:heading size="sm">Branding</flux:heading>
                        <flux:text class="text-sm">Your logo appears on invoices; your icon appears in the sidebar and the browser tab for your team and your clients. PNG, JPG or WEBP.</flux:text>
                    </div>

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
                                            wire:click="removeBranding('{{ $field }}')" wire:confirm="Remove the {{ strtolower($label) }}?">Remove</flux:button>
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
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveBranding,logo,icon">Save branding</flux:button>
                    </div>
                </form>
            @elseif ($tab === 'mpesa')
                <livewire:vendor.settings.mpesa />
            @elseif ($tab === 'sms')
                <livewire:vendor.settings.sms />
            @elseif ($tab === 'prepaid')
                <livewire:vendor.settings.prepaid />
            @elseif ($tab === 'smart-meters')
                <livewire:vendor.settings.smart-meters />
            @elseif ($tab === 'integrations')
                @php
                    $savedKey = $workspace->gemini_api_key;
                    $usesPlatform = blank($savedKey) && config('services.gemini.platform_fallback') && filled(config('services.gemini.key'));
                @endphp

                <div class="space-y-6">
                    <div class="app-card space-y-5 p-6">
                        <div class="flex items-start gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-500 text-white shadow-sm">
                                <flux:icon.sparkles variant="mini" class="size-5" />
                            </span>
                            <div>
                                <flux:heading size="sm">Gemini — automatic meter reading</flux:heading>
                                <flux:text class="text-sm">
                                    Photos of meters are read by Google Gemini using <strong>your own</strong> API key, so your
                                    usage and quota are yours alone. Create a free key at
                                    <flux:link href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</flux:link>.
                                </flux:text>
                            </div>
                        </div>

                        {{-- Status --}}
                        @if (filled($savedKey))
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <span class="flex items-center gap-2">
                                    <flux:icon.check-circle variant="mini" class="size-5" />
                                    Your key ending in <span class="font-mono font-semibold">••••{{ substr($savedKey, -4) }}</span> is in use.
                                </span>
                                <span class="flex gap-2">
                                    <flux:button size="sm" variant="filled" icon="signal" wire:click="testGeminiKey" wire:loading.attr="disabled" wire:target="testGeminiKey">Test key</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600" wire:click="removeGeminiKey"
                                        wire:confirm="Remove your Gemini API key? Automatic meter reading will stop until you add another.">Remove</flux:button>
                                </span>
                            </div>
                        @elseif ($usesPlatform)
                            <div class="flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300">
                                <flux:icon.information-circle variant="mini" class="size-5 shrink-0" />
                                You are using the shared platform key for now. Add your own to get your own quota.
                            </div>
                        @else
                            <div class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                                <flux:icon.exclamation-triangle variant="mini" class="size-5 shrink-0" />
                                No key yet — automatic meter reading is off. Readings can still be entered by hand.
                            </div>
                        @endif

                        {{-- Add / replace --}}
                        <form wire:submit="saveGeminiKey" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <flux:input type="password" wire:model="gemini_key" viewable autocomplete="off"
                                    :label="filled($savedKey) ? 'Replace API key' : 'Gemini API key'" placeholder="AIza…" />
                            </div>
                            <flux:button type="submit" variant="primary" icon="key" wire:loading.attr="disabled" wire:target="saveGeminiKey">
                                <span wire:loading.remove wire:target="saveGeminiKey">Check &amp; save</span>
                                <span wire:loading wire:target="saveGeminiKey">Checking…</span>
                            </flux:button>
                        </form>
                        <flux:error name="gemini_key" />

                        <flux:text class="text-xs">
                            The key is checked with Gemini before it is saved, stored encrypted, and never shown again in full.
                        </flux:text>
                    </div>

                    <form wire:submit="saveGeminiModels" class="app-card space-y-4 p-6">
                        <div>
                            <flux:heading size="sm">Models</flux:heading>
                            <flux:text class="text-sm">
                                Which Gemini models read your meter photos, separated by commas. The first is tried first; if it fails the next one is used.
                                Leave blank for the default: <span class="font-mono">{{ implode(', ', config('services.gemini.models', [])) }}</span>.
                            </flux:text>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <flux:input wire:model="gemini_models" label="Models (in order)" class="font-mono"
                                    placeholder="gemini-flash-lite-latest,gemini-flash-latest" />
                            </div>
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveGeminiModels">Save models</flux:button>
                        </div>
                        <flux:error name="gemini_models" />
                    </form>
                </div>
            @elseif ($tab === 'profile')
                <form wire:submit="saveUser" class="app-card space-y-6 p-6">
                    <div>
                        <flux:heading size="sm">Your profile</flux:heading>
                        <flux:text class="text-sm">Personal account used to sign in to this workspace.</flux:text>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="user_name" label="Your name" />
                        <flux:input wire:model="user_email" type="email" label="Email" />
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:text class="text-sm">
                            To change your password, go to
                            <flux:link :href="route('profile.edit')" wire:navigate>your profile settings</flux:link>.
                        </flux:text>
                        <flux:button type="submit" variant="primary">Save profile</flux:button>
                    </div>
                </form>
            @else
                <form wire:submit="saveVendor" class="app-card space-y-6 p-6">
                    <div>
                        <flux:heading size="sm">Workspace</flux:heading>
                        <flux:text class="text-sm">These details appear on every invoice you send to clients.</flux:text>
                    </div>

                    <flux:input wire:model="name" label="Business name" />

                    <flux:radio.group wire:model="business_type" label="What do you bill?">
                        @foreach (\App\Enums\BusinessType::cases() as $type)
                            <flux:radio value="{{ $type->value }}" label="{{ $type->label() }}" description="{{ $type->description() }}" />
                        @endforeach
                    </flux:radio.group>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="email" type="email" label="Business email" />
                        <flux:input wire:model="phone" label="Business phone" />
                        <flux:input wire:model="currency" label="Currency" description="3-letter ISO code, e.g. KES, USD, TZS."
                            maxlength="3" class="uppercase" />
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Save workspace</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
