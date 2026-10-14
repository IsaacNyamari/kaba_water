<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoManager;
use Illuminate\Http\Response;

/** /sitemap.xml and /robots.txt for search engines (generated from Admin → Settings → SEO). */
class SeoController extends Controller
{
    public function sitemap(SeoManager $seo): Response
    {
        return response($seo->sitemapXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(SeoManager $seo): Response
    {
        return response($seo->robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
