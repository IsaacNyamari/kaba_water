@php
    // Admin → Settings → SEO overrides; the view's own title/description are the fallback.
    $seoManager = app(\App\Services\Seo\SeoManager::class);
    $routeName = request()->route()?->getName();
    $seo = $seoManager->meta(
        $routeName,
        trim($__env->yieldContent('title')) ?: null,
        trim($__env->yieldContent('meta_description')) ?: null,
    );
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@include('partials.seo-tags', ['seo' => $seo, 'routeName' => $routeName])
