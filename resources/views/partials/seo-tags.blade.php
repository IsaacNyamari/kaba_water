{{-- Search and social meta tags + structured data. Expects $seo (SeoManager::meta) and $routeName. --}}
<title>{{ $seo['full_title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}" />
@if ($seo['keywords'])
    <meta name="keywords" content="{{ $seo['keywords'] }}" />
@endif
<meta name="robots" content="{{ $seo['robots'] }}" />
<link rel="canonical" href="{{ $seo['canonical'] }}" />
<meta name="theme-color" content="#010C1B" />
@if ($seo['google_verification'])
    <meta name="google-site-verification" content="{{ $seo['google_verification'] }}" />
@endif
@if ($seo['bing_verification'])
    <meta name="msvalidate.01" content="{{ $seo['bing_verification'] }}" />
@endif

<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ $seo['site_name'] }}" />
<meta property="og:title" content="{{ $seo['full_title'] }}" />
<meta property="og:description" content="{{ $seo['description'] }}" />
<meta property="og:url" content="{{ $seo['canonical'] }}" />
<meta property="og:image" content="{{ $seo['image'] }}" />
<meta property="og:image:alt" content="{{ $seo['image_alt'] }}" />
<meta property="og:locale" content="en_KE" />

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $seo['full_title'] }}" />
<meta name="twitter:description" content="{{ $seo['description'] }}" />
<meta name="twitter:image" content="{{ $seo['image'] }}" />
@if ($seo['twitter'])
    <meta name="twitter:site" content="{{ $seo['twitter'] }}" />
@endif

@if ($seo['indexable'])
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => app(\App\Services\Seo\SeoManager::class)->jsonLd($routeName)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
