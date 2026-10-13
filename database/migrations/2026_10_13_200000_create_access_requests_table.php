<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();

            // What the person told us
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();      // water company name (vendor requests)
            $table->string('requested_type');                // vendor | client
            $table->string('provider_name')->nullable();     // client requests: who supplies their water
            $table->string('account_reference')->nullable(); // client requests: their account / meter no.
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Automatic email check
            $table->string('email_check')->nullable();       // App\Enums\EmailCheckResult
            $table->string('email_check_detail')->nullable();

            // Decision
            $table->string('status')->default('pending');    // App\Enums\AccessRequestStatus
            $table->string('role')->nullable();              // role granted on approval
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('auto_approved')->default(false);
            $table->text('rejection_reason')->nullable();

            // Invitation (single-use signup link)
            $table->string('invite_token_hash', 64)->nullable();
            $table->timestamp('invite_sent_at')->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
