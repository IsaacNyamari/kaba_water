<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('journal_entry_id');
            $table->index('account_id');
            $table->index('client_id');
            $table->index('meter_id');
        });

        // A line is either a debit or a credit, never both. Enforced in the DB where
        // supported (MySQL 8.0.16+, SQLite ignores it via ALTER) and always in
        // LedgerService.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                DB::statement(
                    'ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side CHECK (NOT (debit > 0 AND credit > 0))'
                );
            } catch (Throwable) {
                // Older MySQL/MariaDB without CHECK support — the app-level guard still applies.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
