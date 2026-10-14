<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** SEO overrides for one public page, keyed by its route name. Platform-wide (not vendor-scoped). */
class SeoPage extends Model
{
    public const CHANGEFREQS = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    protected $fillable = [
        'key', 'title', 'description', 'keywords', 'og_image',
        'noindex', 'in_sitemap', 'priority', 'changefreq', 'updated_by',
    ];

    protected $casts = [
        'noindex' => 'boolean',
        'in_sitemap' => 'boolean',
        'priority' => 'decimal:1',
    ];
}
