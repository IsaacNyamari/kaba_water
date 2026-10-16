<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Short codes for pay links (/p/{code}) so they fit in an SMS. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->string('kind', 20);              // invoice | token
            $table->unsignedBigInteger('subject_id'); // invoice id or meter id
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
