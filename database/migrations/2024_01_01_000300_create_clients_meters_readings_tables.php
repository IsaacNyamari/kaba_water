<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_no');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active'); // active|inactive
            $table->timestamps();

            $table->unique(['vendor_id', 'account_no']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('serial')->unique();
            $table->string('location')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->string('status')->default('active'); // active|faulty|removed
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reading');
            $table->unsignedBigInteger('previous_reading')->nullable();
            $table->unsignedBigInteger('consumption')->nullable();
            $table->string('source')->default('manual');
            $table->string('photo_path')->nullable();
            $table->dateTime('read_at');                          // ← was ->timestamp()
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['meter_id', 'read_at']);
            $table->index(['vendor_id', 'read_at']);
        });

        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('flat'); // flat|tiered
            $table->decimal('rate', 10, 4)->nullable();
            $table->json('tiers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariffs');
        Schema::dropIfExists('readings');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('clients');
    }
};
