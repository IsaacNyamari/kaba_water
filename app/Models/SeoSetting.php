<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Site-wide SEO settings and the last generated sitemap (a single row). */
class SeoSetting extends Model
{
    protected $fillable = [
        'site_name', 'title_separator', 'default_description', 'default_keywords', 'default_og_image',
        'twitter_handle', 'google_verification', 'bing_verification',
        'sitemap_xml', 'sitemap_url_count', 'sitemap_generated_at',
    ];

    protected $casts = [
        'sitemap_generated_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
