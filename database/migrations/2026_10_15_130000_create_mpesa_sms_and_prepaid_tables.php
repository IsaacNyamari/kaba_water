<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-Pesa (Daraja) payments with auto-reconciliation and a suspense queue,
 * SMS confirmations, prepaid STS token vending and smart-meter telemetry.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per vendor per integration (mpesa, sms, token_vending, smart_meters).
        // Credentials live in the encrypted config; webhook_token routes callbacks to the vendor.
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->boolean('is_enabled')->default(false);
            $table->text('config')->nullable();
            $table->string('webhook_token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'type']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('house_number', 40)->nullable()->after('address');
            $table->index(['vendor_id', 'house_number']);
        });

        Schema::table('meters', function (Blueprint $table) {
            $table->string('billing_mode', 10)->default('postpaid')->after('status'); // postpaid|prepaid
            $table->string('device_id', 64)->nullable()->after('billing_mode');       // DevEUI / IMEI of a smart meter
            $table->string('connectivity', 20)->nullable()->after('device_id');       // lorawan|nbiot|gprs|other
            $table->decimal('last_value', 14, 3)->nullable()->after('connectivity');
            $table->timestamp('last_seen_at')->nullable()->after('last_value');
            $table->unsignedTinyInteger('battery_percent')->nullable()->after('last_seen_at');
            $table->json('alerts')->nullable()->after('battery_percent');

            $table->unique(['vendor_id', 'device_id']);
        });

        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);                       // c2b|stk
            $table->string('purpose', 10)->default('bill');      // bill|token
            $table->string('receipt', 30)->nullable()->unique(); // M-Pesa code, e.g. QWE12RT34Y
            $table->string('checkout_request_id', 80)->nullable()->unique();
            $table->string('merchant_request_id', 80)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('payer_name')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->string('account_reference', 60)->nullable();
            $table->string('short_code', 20)->nullable();
            // pending (STK awaiting PIN) | failed | matched (fully applied) | credit (client known, money left over) | suspense (no client found)
            $table->string('status', 12)->default('pending');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result_code', 10)->nullable();
            $table->string('result_description')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('transacted_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'transacted_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('mpesa_transaction_id')->nullable()->after('reference')->constrained()->nullOnDelete();
            // Settled out of money already held as a customer deposit (suspense / credit), not new cash.
            $table->boolean('from_customer_deposit')->default(false)->after('mpesa_transaction_id');
        });

        Schema::create('prepaid_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mpesa_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_paid', 15, 2);
            $table->decimal('arrears_deducted', 15, 2)->default(0);
            $table->decimal('token_amount', 15, 2);
            $table->decimal('units', 12, 3)->default(0);
            $table->string('token', 40)->nullable();
            $table->string('provider', 30);
            $table->string('provider_reference', 80)->nullable();
            $table->string('status', 10)->default('pending'); // pending|issued|failed
            $table->string('error')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
        });

        Schema::create('meter_telemetry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 14, 3);
            $table->timestamp('read_at');
            $table->unsignedTinyInteger('battery_percent')->nullable();
            $table->smallInteger('signal')->nullable();
            $table->json('alerts')->nullable();
            $table->string('frame_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['meter_id', 'read_at']);
            $table->index(['vendor_id', 'read_at']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 20);
            $table->text('message');
            $table->string('purpose', 30)->nullable();
            $table->string('provider', 20);
            $table->string('status', 12); // sent|failed|logged
            $table->string('provider_message_id', 80)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('meter_telemetry');
        Schema::dropIfExists('prepaid_tokens');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mpesa_transaction_id');
            $table->dropColumn('from_customer_deposit');
        });

        Schema::dropIfExists('mpesa_transactions');

        Schema::table('meters', function (Blueprint $table) {
            $table->dropUnique(['vendor_id', 'device_id']);
            $table->dropColumn(['billing_mode', 'device_id', 'connectivity', 'last_value', 'last_seen_at', 'battery_percent', 'alerts']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'house_number']);
            $table->dropColumn('house_number');
        });

        Schema::dropIfExists('integrations');
    }
};
