<?php

use App\Support\EnvEditor;
use App\Support\EnvSettings;
use App\Support\Workspace;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Platform settings for super admins: branding plus the editable .env values.
 * The route also requires a fresh password confirmation.
 */
new #[Layout('layouts.admin'), Title('Platform settings')] class extends Component {
    use WithFileUploads;

    #[Url(as: 'tab', history: true)]
    public string $tab = 'branding';

    /** Non-secret values, keyed by ENV name. */
    public array $values = [];

    /** Secret inputs: always start empty; an empty input keeps the current value. */
    public array $secrets = [];

    /** Which secrets currently have a value (never the values themselves). */
    public array $secretIsSet = [];

    public $logo = null;
    public $icon = null;

    public string $testRecipient = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->loadValues();
        $this->testRecipient = auth()->user()->email;

        if ($this->tab !== 'branding' && ! array_key_exists($this->tab, EnvSettings::groups())) {
            $this->tab = 'branding';
        }
    }

    protected function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    protected function loadValues(): void
    {
        $env = app(EnvEditor::class)->values();

        foreach (EnvSettings::fields() as $key => $field) {
            $current = $env[$key] ?? '';

            if ($field['type'] === 'secret') {
                $this->secrets[$key] = '';
                $this->secretIsSet[$key] = $current !== '';
            } elseif ($field['type'] === 'bool') {
                $this->values[$key] = in_array(strtolower($current), ['true', '1', 'yes', 'on'], true);
            } else {
                $this->values[$key] = $current === 'null' ? '' : $current;
            }
        }
    }

    public function saveGroup(string $group): void
    {
        $this->authorizeAdmin();

        $definition = EnvSettings::groups()[$group] ?? abort(404);
        $fields = $definition['fields'];

        $rules = [];
        $attributes = [];
        foreach ($fields as $key => $field) {
            $prefix = $field['type'] === 'secret' ? 'secrets' : 'values';
            $rules["{$prefix}.{$key}"] = $field['type'] === 'bool' ? ['boolean'] : ($field['rules'] ?? ['nullable', 'string', 'max:255']);
            $attributes["{$prefix}.{$key}"] = $field['label'];
        }
        $this->validate($rules, [], $attributes);

        $current = app(EnvEditor::class)->values();
        $changes = [];

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'secret') {
                if (($this->secrets[$key] ?? '') !== '') {
                    $changes[$key] = $this->secrets[$key];
                }

                continue;
            }

            $value = $this->values[$key] ?? '';
            $new = match ($field['type']) {
                'bool' => $value ? 'true' : 'false',
                'select' => $value === '' ? 'null' : (string) $value,
                default => trim((string) $value),
            };

            if ($new !== ($current[$key] ?? '')) {
                $changes[$key] = $new;
            }
        }

        if ($changes === []) {
            Flux::toast(variant: 'warning', heading: 'Nothing changed', text: 'No settings were different from the current values.');

            return;
        }

        try {
            app(EnvEditor::class)->set($changes);
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', heading: 'Could not save', text: $e->getMessage());

            return;
        }

        $this->applyChanges(array_keys($changes), restartQueue: in_array($group, ['mail', 'queue', 'services'], true));

        // A fresh page load picks up the new values (name, URL, mailer…).
        session()->flash('status', $definition['title'].' settings saved ('.count($changes).' changed). They apply from the next request.');
        $this->redirectRoute('admin.settings.index', ['tab' => $group]);
    }

    /**
     * Make the new .env values take effect: rebuild cached config if the app
     * uses it, restart queue workers when they hold the old values, and log
     * who changed what (key names only — never values).
     *
     * @param  list<string>  $keys
     */
    protected function applyChanges(array $keys, bool $restartQueue = false): void
    {
        if (app()->configurationIsCached()) {
            Artisan::call('config:cache');
        }

        if ($restartQueue) {
            rescue(fn () => Artisan::call('queue:restart'));
        }

        Log::notice('Platform settings changed', [
            'user_id' => auth()->id(),
            'email' => auth()->user()->email,
            'keys' => $keys,
        ]);
    }

    /** Upload the platform logo and/or icon. SVG is refused (it can carry script). */
    public function saveBranding(): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'values.APP_NAME' => ['required', 'string', 'max:60'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64'],
        ], [], ['values.APP_NAME' => 'Platform name']);

        $changes = [];
        $current = app(EnvEditor::class)->values();

        if (trim($this->values['APP_NAME']) !== ($current['APP_NAME'] ?? '')) {
            $changes['APP_NAME'] = trim($this->values['APP_NAME']);
        }

        foreach (['logo' => 'PLATFORM_LOGO', 'icon' => 'PLATFORM_ICON'] as $field => $key) {
            if (! $this->{$field}) {
                continue;
            }

            $changes[$key] = $this->{$field}->store('platform', 'public');

            if (! empty($current[$key])) {
                Storage::disk('public')->delete($current[$key]);
            }
        }

        if ($changes === []) {
            Flux::toast(variant: 'warning', heading: 'Nothing changed');

            return;
        }

        app(EnvEditor::class)->set($changes);
        $this->applyChanges(array_keys($changes));

        session()->flash('status', 'Platform branding updated.');
        $this->redirectRoute('admin.settings.index', ['tab' => 'branding']);
    }

    public function removeBranding(string $field): void
    {
        $this->authorizeAdmin();

        $key = ['logo' => 'PLATFORM_LOGO', 'icon' => 'PLATFORM_ICON'][$field] ?? abort(404);
        $current = app(EnvEditor::class)->values()[$key] ?? '';

        if ($current !== '') {
            Storage::disk('public')->delete($current);
            app(EnvEditor::class)->set([$key => '']);
            $this->applyChanges([$key]);
        }

        session()->flash('status', 'Platform '.$field.' removed.');
        $this->redirectRoute('admin.settings.index', ['tab' => 'branding']);
    }

    /** Send a test email with the mail settings currently in force. */
    public function sendTestEmail(): void
    {
        $this->authorizeAdmin();
        $this->validate(['testRecipient' => ['required', 'email']], [], ['testRecipient' => 'recipient']);

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').'. If you can read it, outgoing mail is working.',
                fn ($message) => $message->to($this->testRecipient)->subject('Test email from '.config('app.name')),
            );
        } catch (\Throwable $e) {
            Log::warning('Test email failed', ['error' => $e->getMessage()]);
            Flux::toast(variant: 'danger', heading: 'Test email failed', text: \Illuminate\Support\Str::limit($e->getMessage(), 200));

            return;
        }

        Flux::toast(variant: 'success', heading: 'Test email sent', text: 'Sent to '.$this->testRecipient.' using the '.config('mail.default').' mailer.');
    }

    public function with(): array
    {
        $env = app(EnvEditor::class)->values();

        return [
            'groups' => EnvSettings::groups(),
            'platformLogo' => Workspace::url($env['PLATFORM_LOGO'] ?? null),
            'platformIcon' => Workspace::url($env['PLATFORM_ICON'] ?? null),
            'configCached' => app()->configurationIsCached(),
            'envWritable' => is_writable(app()->environmentFilePath()),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-brand p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-sm">
                <flux:icon.adjustments-horizontal variant="mini" class="size-5" />
            </span>
            <div>
                <flux:heading size="lg">Platform settings</flux:heading>
                <flux:text class="text-sm">Branding and server settings for the whole platform. Changes are written to the <code class="font-mono">.env</code> file (backed up on every save) and logged.</flux:text>
            </div>
        </div>

        @unless ($envWritable)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4">
                The <code>.env</code> file is not writable by the web server, so settings cannot be saved from here.
            </flux:callout>
        @endunless
    </div>

    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        {{-- Section menu --}}
        <nav class="flex gap-2 overflow-x-auto lg:flex-col lg:overflow-visible" aria-label="Settings sections">
            @foreach (['branding' => ['title' => 'Branding', 'icon' => 'swatch']] + $groups as $key => $group)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}"
                    class="flex shrink-0 items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-left text-sm font-medium transition
                        {{ $tab === $key ? 'bg-brand-500 text-white shadow-sm' : 'text-zinc-600 hover:bg-brand-50 hover:text-brand-700 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-300' }}">
                    <flux:icon :name="$group['icon']" variant="mini" class="size-4" />
                    {{ $group['title'] }}
                    @if (! empty($group['danger']))
                        <flux:icon.exclamation-triangle variant="micro" class="ml-auto size-3.5 {{ $tab === $key ? 'text-white' : 'text-amber-500' }}" />
                    @endif
                </button>
            @endforeach
        </nav>

        {{-- Section body --}}
        <div wire:key="panel-{{ $tab }}">
            @if ($tab === 'branding')
                <form wire:submit="saveBranding" class="app-card space-y-6 p-6">
                    <div>
                        <flux:heading size="sm">Branding</flux:heading>
                        <flux:text class="text-sm">Shown on the admin dashboard and the sign-in pages. Vendor workspaces show their own branding instead.</flux:text>
                    </div>

                    <flux:input wire:model="values.APP_NAME" label="Platform name" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ([['logo', 'Logo', $platformLogo, 'Wide image · PNG, JPG or WEBP · max 2 MB', 'h-20 w-full object-contain'], ['icon', 'Icon', $platformIcon, 'Square, at least 64×64 px · max 1 MB', 'size-20 rounded-2xl object-cover']] as [$field, $label, $currentUrl, $hint, $previewClass])
                            <div wire:key="brand-{{ $field }}" class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <flux:label>{{ $label }}</flux:label>
                                    @if ($currentUrl && ! $this->{$field})
                                        <flux:button size="xs" variant="ghost" icon="trash" class="!text-rose-600"
                                            wire:click="removeBranding('{{ $field }}')" wire:confirm="Remove the platform {{ strtolower($label) }}?">Remove</flux:button>
                                    @endif
                                </div>
                                <label class="flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-200 bg-brand-50/50 p-4 text-center transition hover:border-brand-400 hover:bg-brand-50 dark:border-brand-500/20 dark:bg-brand-500/5 dark:hover:border-brand-400/50">
                                    <input type="file" wire:model="{{ $field }}" accept="image/png,image/jpeg,image/webp" class="hidden" />
                                    @if ($this->{$field})
                                        <img src="{{ $this->{$field}->temporaryUrl() }}" alt="" class="{{ $previewClass }}" />
                                        <span class="text-xs font-medium text-brand-700 dark:text-brand-300">New {{ strtolower($label) }} — save to apply</span>
                                    @elseif ($currentUrl)
                                        <img src="{{ $currentUrl }}" alt="" class="{{ $previewClass }}" />
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
            @else
                @php $group = $groups[$tab]; @endphp

                <form wire:submit="saveGroup('{{ $tab }}')" class="app-card space-y-6 p-6">
                    <div>
                        <flux:heading size="sm">{{ $group['title'] }}</flux:heading>
                        <flux:text class="text-sm">{{ $group['description'] }}</flux:text>
                    </div>

                    @if (! empty($group['danger']))
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            These settings control how the platform connects to its database and storage. A wrong value can
                            make the site unreachable until the <code>.env</code> file is fixed on the server
                            (backups are kept in <code>storage/app/env-backups</code>).
                        </flux:callout>
                    @endif

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($group['fields'] as $key => $field)
                            <div wire:key="field-{{ $key }}" class="{{ in_array($field['type'], ['bool']) ? 'sm:col-span-2' : '' }}">
                                @switch($field['type'])
                                    @case('bool')
                                        <flux:switch wire:model="values.{{ $key }}" :label="$field['label']" :description="$field['help'] ?? null" />
                                        @break

                                    @case('select')
                                        <flux:select wire:model="values.{{ $key }}" :label="$field['label']" :description="$field['help'] ?? null">
                                            @foreach ($field['options'] as $value => $label)
                                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                        @break

                                    @case('secret')
                                        <flux:input type="password" wire:model="secrets.{{ $key }}" :label="$field['label']" autocomplete="new-password"
                                            :placeholder="$secretIsSet[$key] ? '•••••••• (set — leave blank to keep)' : 'Not set'" :description="$field['help'] ?? null" viewable />
                                        @break

                                    @default
                                        <flux:input wire:model="values.{{ $key }}" :type="$field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text'))"
                                            :label="$field['label']" :description="$field['help'] ?? null" />
                                @endswitch
                                <div class="mt-1 font-mono text-[11px] text-zinc-400">{{ $key }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:text class="text-xs">
                            @if ($configCached)
                                Config is cached; it will be rebuilt automatically after saving.
                            @else
                                Changes apply from the next page load.
                            @endif
                        </flux:text>
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveGroup">Save {{ strtolower($group['title']) }}</flux:button>
                    </div>
                </form>

                @if ($tab === 'mail')
                    <form wire:submit="sendTestEmail" class="app-card mt-6 space-y-4 p-6">
                        <div>
                            <flux:heading size="sm">Send a test email</flux:heading>
                            <flux:text class="text-sm">Uses the saved mail settings. Save first, then test.</flux:text>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1"><flux:input type="email" wire:model="testRecipient" label="Send to" /></div>
                            <flux:button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="sendTestEmail">Send test</flux:button>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>
