<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('reference')->nullable();
            $table->text('memo')->nullable();

            // What produced the entry (e.g. App\Models\Invoice #12, event "invoice.issued").
            // source_event lets one document own several entries (issue, void, write-off)
            // while keeping auto-posting idempotent.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 60)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reverses_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['source_type', 'source_id', 'source_event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
