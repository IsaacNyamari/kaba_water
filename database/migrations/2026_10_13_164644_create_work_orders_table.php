<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            // Who/where
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // What
            $table->string('number')->nullable();     // WO-2026-0001
            $table->string('type');                   // repair|installation|maintenance|inspection|disconnection|reconnection|other
            $table->string('priority')->default('normal'); // low|normal|high|urgent
            $table->string('status')->default('pending');  // pending|assigned|in_progress|completed|cancelled
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();

            // Timing
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Costing
            $table->decimal('materials_cost', 12, 2)->default(0);
            $table->decimal('labor_cost', 12, 2)->default(0);

            // Billing
            $table->boolean('billable')->default(false);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'type']);
            $table->index(['client_id', 'status']);
        });

        // Now add the FK from expenses.work_order_id → work_orders.id
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('work_order_id')->references('id')->on('work_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['work_order_id']);
        });

        Schema::dropIfExists('work_orders');
    }
};
