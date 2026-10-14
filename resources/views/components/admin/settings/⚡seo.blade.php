<?php

use App\Models\SeoPage;
use App\Models\SeoSetting;
use App\Services\GeminiKeyMissingException;
use App\Services\Seo\GeminiSeoWriter;
use App\Services\Seo\SeoManager;
use App\Support\Seo\SeoRegistry;
use App\Support\Workspace;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Admin → Settings → SEO: site-wide defaults, per-page titles/descriptions/
 * keywords/share images (with Gemini drafts), and the sitemap for Search Console.
 */
new class extends Component {
    use WithFileUploads;

    // Site-wide
    public string $site_name = '';
    public string $title_separator = '·';
    public string $default_description = '';
    public string $default_keywords = '';
    public string $twitter_handle = '';
    public string $google_verification = '';
    public string $bing_verification = '';
    public $default_image = null;

    // Page editor
    public ?string $editingKey = null;
    public string $title = '';
    public string $description = '';
    public string $keywords = '';
    public bool $noindex = false;
    public bool $in_sitemap = true;
    public string $priority = '0.5';
    public string $changefreq = 'monthly';
    public $og_image = null;
    public bool $removeImage = false;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $s = SeoSetting::current();
        $this->site_name = (string) $s->site_name;
        $this->title_separator = $s->title_separator ?: '·';
        $this->default_description = (string) $s->default_description;
        $this->default_keywords = (string) $s->default_keywords;
        $this->twitter_handle = (string) $s->twitter_handle;
        $this->google_verification = (string) $s->google_verification;
        $this->bing_verification = (string) $s->bing_verification;
    }

    protected function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    /** Accept either the bare code or the whole <meta …content="…"> tag Search Console gives you. */
    protected function verificationCode(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/content=["\']([^"\']+)["\']/i', $value, $m)) {
            $value = $m[1];
        }

        return $value !== '' ? $value : null;
    }

    public function saveSite(): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'site_name' => ['nullable', 'string', 'max:80'],
            'title_separator' => ['required', 'string', 'max:5'],
            'default_description' => ['nullable', 'string', 'max:320'],
            'default_keywords' => ['nullable', 'string', 'max:255'],
            'twitter_handle' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9_]{1,15}$/'],
            'google_verification' => ['nullable', 'string', 'max:300'],
            'bing_verification' => ['nullable', 'string', 'max:300'],
            'default_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072', 'dimensions:min_width=600,min_height=315'],
        ], [], ['default_image' => 'default share image']);

        $settings = SeoSetting::current();
        $data = [
            'site_name' => trim($this->site_name) ?: null,
            'title_separator' => trim($this->title_separator),
            'default_description' => trim($this->default_description) ?: null,
            'default_keywords' => trim($this->default_keywords) ?: null,
            'twitter_handle' => trim($this->twitter_handle) ? ltrim(trim($this->twitter_handle), '@') : null,
            'google_verification' => $this->verificationCode($this->google_verification),
            'bing_verification' => $this->verificationCode($this->bing_verification),
        ];

        if ($this->default_image) {
            $data['default_og_image'] = $this->default_image->store('seo', 'public');
            if ($settings->default_og_image) {
                Storage::disk('public')->delete($settings->default_og_image);
            }
        }

        $settings->update($data);
        $this->google_verification = (string) $data['google_verification'];
        $this->bing_verification = (string) $data['bing_verification'];
        $this->reset('default_image');
        $this->afterChange('Site-wide SEO saved.');
    }

    public function removeDefaultImage(): void
    {
        $this->authorizeAdmin();
        $settings = SeoSetting::current();
        if ($settings->default_og_image) {
            Storage::disk('public')->delete($settings->default_og_image);
            $settings->update(['default_og_image' => null]);
        }
        Flux::toast(variant: 'success', text: 'Default share image removed — the HumSols image is used.');
    }

    public function edit(string $key): void
    {
        $this->authorizeAdmin();
        $default = SeoRegistry::page($key) ?? abort(404);
        $page = SeoPage::firstWhere('key', $key);

        $this->editingKey = $key;
        $this->title = (string) ($page?->title ?? '');
        $this->description = (string) ($page?->description ?? '');
        $this->keywords = (string) ($page?->keywords ?? '');
        $this->noindex = $page ? $page->noindex : (bool) ($default['noindex'] ?? false);
        $this->in_sitemap = $page ? $page->in_sitemap : true;
        $this->priority = number_format((float) ($page?->priority ?? $default['priority']), 1, '.', '');
        $this->changefreq = $page?->changefreq ?? $default['changefreq'];
        $this->reset('og_image', 'removeImage');
        $this->resetValidation();
        Flux::modal('seo-page')->show();
    }

    /** Ask Gemini for a draft from the page's own text. Fills the form; nothing is saved yet. */
    public function suggest(): void
    {
        $this->authorizeAdmin();
        abort_unless(SeoRegistry::has($this->editingKey), 404);

        try {
            $draft = app(GeminiSeoWriter::class)->suggest($this->editingKey);
        } catch (GeminiKeyMissingException) {
            Flux::toast(variant: 'warning', heading: 'No Gemini key', text: 'Add the platform Gemini API key under Settings → Services first.');

            return;
        } catch (\Throwable $e) {
            Log::warning('SEO suggestion failed', ['page' => $this->editingKey, 'error' => $e->getMessage()]);
            Flux::toast(variant: 'danger', heading: 'Gemini could not help right now', text: \Illuminate\Support\Str::limit($e->getMessage(), 180));

            return;
        }

        $this->title = $draft['title'];
        $this->description = $draft['description'];
        $this->keywords = $draft['keywords'];
        Flux::toast(variant: 'success', heading: 'Draft ready', text: 'Review the suggestion, edit if needed, then save.');
    }

    public function savePage(): void
    {
        $this->authorizeAdmin();
        abort_unless(SeoRegistry::has($this->editingKey), 404);

        $this->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:320'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'noindex' => ['boolean'],
            'in_sitemap' => ['boolean'],
            'priority' => ['required', Rule::in(['0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0'])],
            'changefreq' => ['required', Rule::in(SeoPage::CHANGEFREQS)],
            'og_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072', 'dimensions:min_width=600,min_height=315'],
        ], [], ['og_image' => 'share image']);

        $page = SeoPage::firstOrNew(['key' => $this->editingKey]);
        $image = $page->og_image;

        if ($this->og_image) {
            $image = $this->og_image->store('seo', 'public');
        } elseif ($this->removeImage) {
            $image = null;
        }
        if ($page->og_image && $image !== $page->og_image) {
            Storage::disk('public')->delete($page->og_image);
        }

        $page->fill([
            'title' => trim($this->title) ?: null,
            'description' => trim($this->description) ?: null,
            'keywords' => trim($this->keywords) ?: null,
            'og_image' => $image,
            'noindex' => $this->noindex,
            'in_sitemap' => $this->in_sitemap,
            'priority' => $this->priority,
            'changefreq' => $this->changefreq,
            'updated_by' => auth()->id(),
        ])->save();

        Flux::modal('seo-page')->close();
        $this->afterChange(SeoRegistry::page($this->editingKey)['label'].' SEO saved.');
        $this->reset('og_image', 'removeImage');
    }

    public function resetSeoPage(string $key): void
    {
        $this->authorizeAdmin();
        $page = SeoPage::firstWhere('key', $key);
        if ($page) {
            if ($page->og_image) {
                Storage::disk('public')->delete($page->og_image);
            }
            $page->delete();
        }
        $this->afterChange(SeoRegistry::page($key)['label'].' is back to its default SEO.');
    }

    public function generateSitemap(): void
    {
        $this->authorizeAdmin();
        $seo = app(SeoManager::class);
        $seo->flush();
        $settings = $seo->generateSitemap();
        Log::notice('Sitemap generated', ['user_id' => auth()->id(), 'urls' => $settings->sitemap_url_count]);
        Flux::toast(variant: 'success', heading: 'Sitemap generated', text: $settings->sitemap_url_count.' pages. Submit '.$seo->sitemapUrl().' in Google Search Console.');
    }

    /** Saving SEO keeps an already generated sitemap in step automatically. */
    protected function afterChange(string $message): void
    {
        $seo = app(SeoManager::class);
        $seo->flush();
        if (SeoSetting::current()->sitemap_generated_at) {
            $seo->generateSitemap();
            $message .= ' Sitemap updated.';
        }
        Flux::toast(variant: 'success', text: $message);
    }

    public function with(): array
    {
        $seo = app(SeoManager::class);
        $seo->flush();
        $settings = $seo->settings();
        $overrides = $seo->overrides();

        $pages = collect(SeoRegistry::pages())->map(function ($default, $key) use ($seo, $overrides) {
            $meta = $seo->meta($key);

            return [
                'key' => $key,
                'label' => $default['label'],
                'url' => route($key),
                'meta' => $meta,
                'custom' => $overrides->has($key),
                'in_sitemap' => $meta['indexable'] && ($overrides->get($key)?->in_sitemap ?? true),
                'image' => $meta['image'],
            ];
        });

        $editing = $this->editingKey ? SeoRegistry::page($this->editingKey) : null;

        return [
            'pages' => $pages,
            'settings' => $settings,
            'defaultImage' => $settings->default_og_image ? Workspace::url($settings->default_og_image) : null,
            'sitemapUrl' => $seo->sitemapUrl(),
            'robotsUrl' => $seo->canonical('robots.txt'),
            'entries' => $seo->sitemapEntries(),
            'sitemapXml' => $settings->sitemap_xml,
            'geminiReady' => filled(config('services.gemini.key')),
            'editing' => $editing,
            'editingImage' => $this->editingKey ? $overrides->get($this->editingKey)?->og_image : null,
            'siteName' => $seo->siteName(),
        ];
    }
};
?>

<div class="space-y-6">
    {{-- ═══ Sitemap & Search Console ═══ --}}
    <div class="app-card space-y-5 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="sm">Sitemap & Google Search Console</flux:heading>
                <flux:text class="text-sm">Generate the sitemap, then submit its URL in Search Console so Google finds every public page.</flux:text>
            </div>
            <flux:button variant="primary" icon="arrow-path" wire:click="generateSitemap" wire:loading.attr="disabled" wire:target="generateSitemap">
                {{ $settings->sitemap_generated_at ? 'Regenerate sitemap' : 'Generate sitemap' }}
            </flux:button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([['Sitemap URL', $sitemapUrl], ['robots.txt', $robotsUrl]] as [$label, $url])
                <div wire:key="url-{{ $loop->index }}" x-data="{ copied: false }" class="rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                    <div class="text-xs font-medium text-zinc-500">{{ $label }}</div>
                    <div class="mt-1 flex items-center gap-2">
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate font-mono text-sm text-brand-700 hover:underline dark:text-brand-300">{{ $url }}</a>
                        <flux:button size="xs" icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($url)); copied = true; setTimeout(() => copied = false, 1500)">
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="text-sm text-zinc-600 dark:text-zinc-300">
            @if ($settings->sitemap_generated_at)
                Last generated {{ $settings->sitemap_generated_at->diffForHumans() }} · <strong>{{ $settings->sitemap_url_count }}</strong> pages.
            @else
                <span class="text-amber-600">Not generated yet</span> — until then /sitemap.xml is built on the fly.
            @endif
        </div>

        <ol class="list-decimal space-y-1 pl-5 text-sm text-zinc-600 dark:text-zinc-300">
            <li>Open <a href="https://search.google.com/search-console" target="_blank" rel="noopener" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Google Search Console</a> and add the property <span class="font-mono">{{ rtrim(config('app.url'), '/') }}</span>.</li>
            <li>Choose the <strong>HTML tag</strong> verification method and paste the tag (or its code) into <em>Google verification</em> below, save, then press Verify in Search Console.</li>
            <li>Go to <strong>Sitemaps</strong>, paste the sitemap URL above and press Submit. Regenerate here after adding pages; Google re-reads it automatically.</li>
        </ol>

        @if ($entries)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-zinc-500"><th class="py-2 pr-3">Page</th><th class="py-2 pr-3">URL</th><th class="py-2 pr-3">Last modified</th><th class="py-2 pr-3">Frequency</th><th class="py-2">Priority</th></tr></thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-white/10">
                        @foreach ($entries as $entry)
                            <tr wire:key="entry-{{ $loop->index }}"><td class="py-2 pr-3 font-medium">{{ $entry['label'] }}</td><td class="py-2 pr-3 font-mono text-xs">{{ $entry['loc'] }}</td><td class="py-2 pr-3">{{ $entry['lastmod'] }}</td><td class="py-2 pr-3">{{ $entry['changefreq'] }}</td><td class="py-2 font-mono">{{ $entry['priority'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($sitemapXml)
            <details x-data="{ copied: false }" class="rounded-xl border border-zinc-200 dark:border-white/10">
                <summary class="cursor-pointer px-4 py-2.5 text-sm font-medium">Sitemap XML</summary>
                <div class="space-y-2 border-t border-zinc-200 p-3 dark:border-white/10">
                    <flux:button size="xs" icon="clipboard" x-on:click="navigator.clipboard.writeText($refs.xml.textContent); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? 'Copied' : 'Copy XML'"></span></flux:button>
                    <pre x-ref="xml" class="max-h-72 overflow-auto rounded-lg bg-zinc-50 p-3 font-mono text-xs dark:bg-white/5">{{ $sitemapXml }}</pre>
                </div>
            </details>
        @endif
    </div>

    {{-- ═══ Pages ═══ --}}
    <div class="app-card space-y-4 p-6">
        <div>
            <flux:heading size="sm">Pages</flux:heading>
            <flux:text class="text-sm">The title and description Google shows for each public page. Blank fields use the page's built-in defaults.</flux:text>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($pages as $page)
                @php $m = $page['meta']; @endphp
                <div wire:key="seo-page-{{ $page['key'] }}" class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $page['label'] }}</span>
                            <flux:badge size="sm" :color="$page['custom'] ? 'emerald' : 'zinc'">{{ $page['custom'] ? 'Custom' : 'Default' }}</flux:badge>
                            @unless ($m['indexable']) <flux:badge size="sm" color="amber">noindex</flux:badge> @endunless
                            @if ($page['in_sitemap']) <flux:badge size="sm" color="sky">In sitemap</flux:badge> @endif
                        </div>
                        <div class="flex shrink-0 gap-1">
                            @if ($page['custom'])
                                <flux:button size="xs" variant="ghost" wire:click="resetSeoPage('{{ $page['key'] }}')" wire:confirm="Remove the custom SEO for {{ $page['label'] }} and use the defaults?">Reset</flux:button>
                            @endif
                            <flux:button size="xs" icon="pencil-square" wire:click="edit('{{ $page['key'] }}')">Edit</flux:button>
                        </div>
                    </div>

                    {{-- Google result preview --}}
                    <div class="mt-3 rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <div class="truncate text-xs text-emerald-700 dark:text-emerald-400">{{ $page['url'] }}</div>
                        <div class="truncate text-[15px] font-medium text-blue-700 dark:text-blue-300">{{ $m['full_title'] }}</div>
                        <div class="line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400">{{ $m['description'] }}</div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-zinc-500">
                        <span class="{{ mb_strlen($m['full_title']) > 60 ? 'text-amber-600' : '' }}">Title {{ mb_strlen($m['full_title']) }}/60</span>
                        <span class="{{ mb_strlen($m['description']) > 160 || mb_strlen($m['description']) < 70 ? 'text-amber-600' : '' }}">Description {{ mb_strlen($m['description']) }}/160</span>
                        <span class="truncate">Keywords: {{ $m['keywords'] ?: '—' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ═══ Site-wide ═══ --}}
    <form wire:submit="saveSite" class="app-card space-y-5 p-6">
        <div>
            <flux:heading size="sm">Site-wide SEO</flux:heading>
            <flux:text class="text-sm">Used on every page unless the page sets its own.</flux:text>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="site_name" label="Site name in titles" :placeholder="config('app.name')" description="Appended to every page title." />
            <flux:input wire:model="title_separator" label="Title separator" description="Between page title and site name, e.g. · | –" />
            <flux:textarea wire:model="default_description" label="Default description" rows="2" class="sm:col-span-2" />
            <flux:input wire:model="default_keywords" label="Default keywords" description="Comma-separated." class="sm:col-span-2" />
            <flux:input wire:model="google_verification" label="Google verification" placeholder='<meta name="google-site-verification" content="…">' description="Paste the HTML tag or just its code." />
            <flux:input wire:model="bing_verification" label="Bing verification" placeholder='<meta name="msvalidate.01" content="…">' />
            <flux:input wire:model="twitter_handle" label="X (Twitter) handle" placeholder="@humsols" />
        </div>

        <div class="space-y-2">
            <flux:label>Default share image</flux:label>
            <div class="flex flex-wrap items-center gap-4">
                <img src="{{ $default_image ? $default_image->temporaryUrl() : ($defaultImage ?? asset('images/brand/og-default.png')) }}" alt="" class="h-24 w-auto rounded-lg ring-1 ring-zinc-200 dark:ring-white/10" />
                <div class="space-y-2">
                    <input type="file" wire:model="default_image" accept="image/png,image/jpeg,image/webp" class="block text-sm" />
                    <flux:text class="text-xs">Shown when a page is shared on WhatsApp, Facebook, LinkedIn or X. Best size 1200×630.</flux:text>
                    @if ($defaultImage)
                        <flux:button size="xs" variant="ghost" icon="trash" class="!text-rose-600" wire:click="removeDefaultImage">Use the HumSols image</flux:button>
                    @endif
                </div>
            </div>
            <flux:error name="default_image" />
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveSite,default_image">Save site-wide SEO</flux:button>
        </div>
    </form>

    {{-- ═══ Page editor ═══ --}}
    <flux:modal name="seo-page" class="w-full max-w-2xl">
        @if ($editing)
            <form wire:submit="savePage" class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">SEO · {{ $editing['label'] }}</flux:heading>
                        <flux:subheading class="font-mono text-xs">{{ route($editingKey) }}</flux:subheading>
                    </div>
                    <flux:button type="button" icon="sparkles" wire:click="suggest" wire:loading.attr="disabled" wire:target="suggest" :disabled="! $geminiReady">
                        <span wire:loading.remove wire:target="suggest">Write with Gemini</span>
                        <span wire:loading wire:target="suggest">Reading the page…</span>
                    </flux:button>
                </div>
                @unless ($geminiReady)
                    <flux:callout icon="information-circle" color="sky"><flux:callout.text>Add the platform Gemini API key under <strong>Settings → Services</strong> to draft titles and descriptions automatically.</flux:callout.text></flux:callout>
                @endunless

                <div>
                    <flux:input wire:model.live.debounce.300ms="title" label="Title" :placeholder="$editing['title']" />
                    <div class="mt-1 text-right text-[11px] {{ mb_strlen($title) > 60 ? 'text-amber-600' : 'text-zinc-500' }}">{{ mb_strlen($title) }}/60 · shown as “{{ ($title ?: $editing['title']).' '.($title_separator ?: '·').' '.$siteName }}”</div>
                </div>
                <div>
                    <flux:textarea wire:model.live.debounce.300ms="description" label="Meta description" rows="3" :placeholder="$editing['description']" />
                    <div class="mt-1 text-right text-[11px] {{ mb_strlen($description) > 160 ? 'text-amber-600' : 'text-zinc-500' }}">{{ mb_strlen($description) }}/160 (aim for 140–160)</div>
                </div>
                <flux:input wire:model="keywords" label="Keywords" :placeholder="$editing['keywords'] ?: 'comma, separated, phrases'" description="Comma-separated search phrases." />

                {{-- Live Google preview --}}
                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                    <div class="text-[11px] uppercase tracking-wide text-zinc-500">Google preview</div>
                    <div class="truncate text-xs text-emerald-700 dark:text-emerald-400">{{ route($editingKey) }}</div>
                    <div class="truncate text-[15px] font-medium text-blue-700 dark:text-blue-300">{{ ($title ?: $editing['title']).' '.($title_separator ?: '·').' '.$siteName }}</div>
                    <div class="line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400">{{ $description ?: $editing['description'] }}</div>
                </div>

                <div class="space-y-2">
                    <flux:label>Share image</flux:label>
                    <div class="flex flex-wrap items-center gap-4">
                        @php $preview = $og_image ? $og_image->temporaryUrl() : (! $removeImage && $editingImage ? Workspace::url($editingImage) : null); @endphp
                        <img src="{{ $preview ?? ($defaultImage ?? asset('images/brand/og-default.png')) }}" alt="" class="h-20 w-auto rounded-lg ring-1 ring-zinc-200 dark:ring-white/10" />
                        <div class="space-y-2">
                            <input type="file" wire:model="og_image" accept="image/png,image/jpeg,image/webp" class="block text-sm" />
                            <flux:text class="text-xs">{{ $preview ? 'This page’s own image.' : 'Using the site-wide image.' }} Best size 1200×630.</flux:text>
                            @if ($editingImage && ! $og_image && ! $removeImage)
                                <flux:button size="xs" variant="ghost" icon="trash" class="!text-rose-600" wire:click="$set('removeImage', true)">Use the site-wide image</flux:button>
                            @endif
                        </div>
                    </div>
                    <flux:error name="og_image" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:switch wire:model="noindex" label="Hide from search engines (noindex)" />
                    <flux:switch wire:model="in_sitemap" label="Include in sitemap" />
                    <flux:select wire:model="priority" label="Sitemap priority">
                        @foreach (['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1'] as $p)
                            <flux:select.option value="{{ $p }}">{{ $p }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="changefreq" label="Changes">
                        @foreach (\App\Models\SeoPage::CHANGEFREQS as $f)
                            <flux:select.option value="{{ $f }}">{{ ucfirst($f) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="savePage,og_image">Save page SEO</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
