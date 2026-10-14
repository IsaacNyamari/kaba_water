<?php

namespace App\Services\Seo;

use App\Models\SeoPage;
use App\Models\SeoSetting;
use App\Support\Seo\SeoRegistry;
use App\Support\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Everything search engines see: meta tags per page, structured data
 * (JSON-LD), the sitemap and robots.txt.
 *
 * Per page, the order is: the admin's override (Settings → SEO) → the value
 * the view sets → the registry default → the site-wide default.
 */
class SeoManager
{
    protected ?SeoSetting $settings = null;

    /** @var Collection<string, SeoPage>|null */
    protected ?Collection $pages = null;

    public function settings(): SeoSetting
    {
        return $this->settings ??= $this->tablesExist() ? SeoSetting::current() : new SeoSetting;
    }

    /** @return Collection<string, SeoPage> */
    public function overrides(): Collection
    {
        return $this->pages ??= $this->tablesExist() ? SeoPage::all()->keyBy('key') : collect();
    }

    /** Forget cached rows after the admin saves. */
    public function flush(): void
    {
        $this->settings = null;
        $this->pages = null;
    }

    public function siteName(): string
    {
        return $this->settings()->site_name ?: (string) config('app.name');
    }

    /**
     * Resolved meta tags for a page.
     *
     * @return array{title: string, full_title: string, description: string, keywords: string, image: string, image_alt: string, canonical: string, robots: string, indexable: bool, site_name: string, twitter: ?string, google_verification: ?string, bing_verification: ?string}
     */
    public function meta(?string $routeName, ?string $viewTitle = null, ?string $viewDescription = null): array
    {
        $settings = $this->settings();
        $default = SeoRegistry::page($routeName);
        $override = $routeName ? $this->overrides()->get($routeName) : null;
        $siteName = $this->siteName();

        $title = $this->first($override?->title, $viewTitle, $default['title'] ?? null) ?? $siteName;
        $separator = $settings->title_separator ?: '·';
        $fullTitle = $title === $siteName || str_contains($title, $siteName) ? $title : "{$title} {$separator} {$siteName}";

        $indexable = $this->isIndexable($routeName);

        return [
            'title' => $title,
            'full_title' => $fullTitle,
            'description' => $this->first($override?->description, $viewDescription, $default['description'] ?? null, $settings->default_description, config('marketing.tagline')) ?? '',
            'keywords' => $this->first($override?->keywords, $default['keywords'] ?? null, $settings->default_keywords) ?? '',
            'image' => $this->imageUrl($override?->og_image ?: $settings->default_og_image),
            'image_alt' => "{$siteName} — {$title}",
            'canonical' => $this->canonical(),
            'robots' => $indexable ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, follow',
            'indexable' => $indexable,
            'site_name' => $siteName,
            'twitter' => $settings->twitter_handle ? '@'.ltrim($settings->twitter_handle, '@') : null,
            'google_verification' => $settings->google_verification,
            'bing_verification' => $settings->bing_verification,
        ];
    }

    /** A page may be indexed when it is public and not switched to noindex. */
    public function isIndexable(?string $routeName): bool
    {
        if (! SeoRegistry::has($routeName)) {
            return false;
        }

        $override = $this->overrides()->get($routeName);

        return ! ($override ? $override->noindex : (SeoRegistry::page($routeName)['noindex'] ?? false));
    }

    /** The canonical URL: the site's own host (APP_URL), the current path, no query string. */
    public function canonical(?string $path = null): string
    {
        $path = $path ?? request()->path();

        return rtrim((string) config('app.url'), '/').($path === '/' || $path === '' ? '/' : '/'.ltrim($path, '/'));
    }

    public function imageUrl(?string $path): string
    {
        $url = $path ? Workspace::url($path) : asset('images/brand/og-default.png');

        // Social networks need an absolute URL on the public host.
        return str_starts_with($url, 'http') ? preg_replace('#^https?://[^/]+#', rtrim((string) config('app.url'), '/'), $url) : $url;
    }

    /**
     * Structured data for a page: the organisation and website everywhere,
     * the product on the sales pages, FAQs on pricing and breadcrumbs below home.
     *
     * @return list<array<string, mixed>>
     */
    public function jsonLd(?string $routeName): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $contact = config('marketing.contact', []);
        $siteName = $this->siteName();

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $base.'/#organization',
                'name' => $siteName,
                'alternateName' => 'HumSols',
                'url' => $base.'/',
                'logo' => asset('images/brand/humsols-logo.png'),
                'email' => $contact['email'] ?? null,
                'telephone' => $contact['phones'][0] ?? null,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Nairobi',
                    'addressCountry' => 'KE',
                ],
                'contactPoint' => collect($contact['phones'] ?? [])->map(fn ($phone) => [
                    '@type' => 'ContactPoint',
                    'telephone' => $phone,
                    'contactType' => 'customer service',
                    'areaServed' => 'KE',
                    'availableLanguage' => ['English', 'Swahili'],
                ])->values()->all(),
                'sameAs' => collect(config('marketing.social', []))->filter(fn ($url) => is_string($url) && str_starts_with($url, 'http'))->values()->all(),
            ],
            [
                '@type' => 'WebSite',
                '@id' => $base.'/#website',
                'url' => $base.'/',
                'name' => $siteName,
                'publisher' => ['@id' => $base.'/#organization'],
                'inLanguage' => 'en-KE',
            ],
        ];

        if (in_array($routeName, ['home', 'marketing.services', 'marketing.pricing'], true)) {
            $graph[] = [
                '@type' => 'SoftwareApplication',
                'name' => 'HS Water Billing',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'url' => $base.'/',
                'publisher' => ['@id' => $base.'/#organization'],
                'description' => $this->meta('home')['description'],
                'offers' => collect(config('marketing.plans', []))
                    ->filter(fn ($plan) => is_numeric($plan['monthly'] ?? null))
                    ->map(fn ($plan) => [
                        '@type' => 'Offer',
                        'name' => $plan['name'],
                        'price' => (string) $plan['monthly'],
                        'priceCurrency' => 'KES',
                        'url' => $base.'/pricing',
                    ])->values()->all(),
            ];
        }

        if ($routeName === 'marketing.pricing' && config('marketing.faqs')) {
            $graph[] = [
                '@type' => 'FAQPage',
                'mainEntity' => collect(config('marketing.faqs'))->map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
                ])->all(),
            ];
        }

        if ($routeName && $routeName !== 'home' && SeoRegistry::has($routeName)) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base.'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => SeoRegistry::page($routeName)['label'], 'item' => $this->canonical()],
                ],
            ];
        }

        return array_map(fn ($node) => array_filter($node, fn ($value) => $value !== null && $value !== []), $graph);
    }

    /**
     * Pages that belong in the sitemap.
     *
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string, label: string}>
     */
    public function sitemapEntries(): array
    {
        $entries = [];

        foreach (SeoRegistry::pages() as $key => $page) {
            $override = $this->overrides()->get($key);

            if (! $this->isIndexable($key) || ($override && ! $override->in_sitemap)) {
                continue;
            }

            $lastModified = collect([
                $override?->updated_at?->getTimestamp(),
                $page['view'] && ($file = $this->viewPath($page['view'])) ? filemtime($file) : null,
            ])->filter()->max() ?? now()->getTimestamp();

            $entries[] = [
                'loc' => $this->canonical(parse_url(route($key), PHP_URL_PATH) ?: '/'),
                'lastmod' => date('Y-m-d', $lastModified),
                'changefreq' => $override?->changefreq ?: $page['changefreq'],
                'priority' => number_format((float) ($override?->priority ?? $page['priority']), 1, '.', ''),
                'label' => $page['label'],
            ];
        }

        return $entries;
    }

    public function buildSitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->sitemapEntries() as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1)."</loc>\n";
            $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$entry['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /** Build the sitemap and store it; /sitemap.xml serves the stored copy. */
    public function generateSitemap(): SeoSetting
    {
        $settings = $this->settings();
        $settings->update([
            'sitemap_xml' => $this->buildSitemap(),
            'sitemap_url_count' => count($this->sitemapEntries()),
            'sitemap_generated_at' => now(),
        ]);

        return $settings;
    }

    /** The stored sitemap, or a freshly built one if none has been generated yet. */
    public function sitemapXml(): string
    {
        return $this->settings()->sitemap_xml ?: $this->buildSitemap();
    }

    public function sitemapUrl(): string
    {
        return $this->canonical('sitemap.xml');
    }

    public function robotsTxt(): string
    {
        $private = ['/admin', '/vendor', '/portal', '/projects', '/modules', '/settings', '/dashboard', '/livewire', '/access/'];

        return "User-agent: *\n"
            ."Allow: /\n"
            .collect($private)->map(fn ($path) => "Disallow: {$path}")->implode("\n")."\n\n"
            .'Sitemap: '.$this->sitemapUrl()."\n";
    }

    protected function viewPath(string $view): ?string
    {
        try {
            return view()->getFinder()->find($view);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function first(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim($value);
            }
        }

        return null;
    }

    protected function tablesExist(): bool
    {
        static $exists = null;

        return $exists ??= rescue(fn () => Schema::hasTable('seo_settings') && Schema::hasTable('seo_pages'), false, false);
    }
}
