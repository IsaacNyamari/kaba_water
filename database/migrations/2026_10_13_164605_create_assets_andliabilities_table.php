<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category'); // vehicle|pump|meter|building|tools|equipment|other
            $table->string('identifier')->nullable();      // plate no, serial, etc.
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 12, 2);
            $table->decimal('salvage_value', 12, 2)->default(0);
            $table->decimal('depreciation_rate', 5, 2)->default(0); // % per year
            $table->string('status')->default('active');   // active|disposed|retired
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'category']);
        });

        Schema::create('liabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category'); // loan|supplier_credit|tax_payable|other
            $table->decimal('principal', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('interest_rate', 5, 2)->default(0); // % per year
            $table->date('started_at');
            $table->date('due_at')->nullable();
            $table->string('status')->default('active'); // active|paid|defaulted
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liabilities');
        Schema::dropIfExists('assets');
    }
};
