@php
    $appName = config('app.name', 'Human Solutions');
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? "{$pageTitle} · {$appName}" : $appName;
    $description = trim($__env->yieldContent('meta_description')) ?: config('marketing.tagline');
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}" />
<link rel="canonical" href="{{ url()->current() }}" />
<meta name="theme-color" content="#0B2545" />

<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ $appName }}" />
<meta property="og:title" content="{{ $fullTitle }}" />
<meta property="og:description" content="{{ $description }}" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:locale" content="en_KE" />

<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="{{ $fullTitle }}" />
<meta name="twitter:description" content="{{ $description }}" />
