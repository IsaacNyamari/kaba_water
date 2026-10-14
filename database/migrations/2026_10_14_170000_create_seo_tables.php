<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO for the public website: per-page overrides (title, description,
 * keywords, share image, indexing) and one row of site-wide settings.
 * Pages without an override keep the defaults written in their views.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique(); // route name, e.g. "marketing.pricing"
            $table->string('title', 120)->nullable();
            $table->string('description', 320)->nullable();
            $table->string('keywords', 255)->nullable();
            $table->string('og_image')->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('in_sitemap')->default(true);
            $table->decimal('priority', 2, 1)->default(0.5);
            $table->string('changefreq', 10)->default('monthly');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 80)->nullable();
            $table->string('title_separator', 5)->default('·');
            $table->string('default_description', 320)->nullable();
            $table->string('default_keywords', 255)->nullable();
            $table->string('default_og_image')->nullable();
            $table->string('twitter_handle', 40)->nullable();
            $table->string('google_verification', 120)->nullable();
            $table->string('bing_verification', 120)->nullable();
            $table->longText('sitemap_xml')->nullable();
            $table->unsignedInteger('sitemap_url_count')->default(0);
            $table->timestamp('sitemap_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
        Schema::dropIfExists('seo_pages');
    }
};
