<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable: super admins have no vendor.
            $table->foreignId('vendor_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('vendors')
                  ->nullOnDelete();

            $table->index(['vendor_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'email']);
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};