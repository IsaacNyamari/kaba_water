<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each vendor's own Gemini API key for meter-photo reading, so one
     * vendor's usage never exhausts the platform's quota. Stored encrypted
     * (the model casts it with APP_KEY), hence text rather than string.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('gemini_api_key')->nullable()->after('icon_path');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('gemini_api_key');
        });
    }
};
