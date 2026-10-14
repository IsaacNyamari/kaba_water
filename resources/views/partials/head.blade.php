<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php $seoRoute = request()->route()?->getName(); @endphp
@if (\App\Support\Seo\SeoRegistry::has($seoRoute))
    {{-- Public page (sign-in, request access): full search and social tags. --}}
    @include('partials.seo-tags', ['seo' => app(\App\Services\Seo\SeoManager::class)->meta($seoRoute, $title ?? null), 'routeName' => $seoRoute])
@else
    <title>
        @php $brandName = \App\Support\Workspace::current()['name']; @endphp
        {{ filled($title ?? null) ? $title.' - '.$brandName : $brandName }}
    </title>
    {{-- Dashboards, portal and admin are private: keep them out of search results. --}}
    <meta name="robots" content="noindex, nofollow" />
@endif

{{-- A workspace icon replaces the default favicons entirely; listing both lets the browser pick the default. --}}
@if ($workspaceIcon = \App\Support\Workspace::current()['icon'])
    <link rel="icon" href="{{ $workspaceIcon }}">
    <link rel="apple-touch-icon" href="{{ $workspaceIcon }}">
@else
    <link rel="icon" href="/favicon.ico?v=hs2" sizes="any">
    <link rel="icon" href="/favicon.svg?v=hs2" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=hs2">
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@include('partials.theme-sync')
