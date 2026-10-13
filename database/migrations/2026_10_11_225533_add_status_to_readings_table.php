<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('source');
            $table->string('rejection_reason')->nullable()->after('status');
            $table->index(['vendor_id', 'status']);
            $table->index(['meter_id', 'status', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'status']);
            $table->dropIndex(['meter_id', 'status', 'read_at']);
            $table->dropColumn(['status', 'rejection_reason']);
        });
    }
};
