<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property owners: a workspace can be a water vendor or a property /
 * apartment owner. Owners bill houses (units) by house number; each house is
 * linked to its tenant (a client) and has a monthly rent and service charge,
 * and can optionally be grouped under a property (building / estate).
 * Meters and invoices can point at the house they belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('business_type', 20)->default('water')->after('name'); // water|property
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'name']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete(); // optional grouping
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete(); // current tenant
            $table->string('house_number', 40);                // e.g. A4, 12 — what bills are addressed to
            $table->string('type', 20)->default('apartment');  // apartment|house|bedsitter|shop|office|other
            $table->decimal('rent', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('deposit', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'house_number']);
            $table->index(['vendor_id', 'client_id']);
        });

        Schema::table('meters', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->string('billing_period', 7)->nullable()->after('reading_id'); // YYYY-MM for rent bills
            $table->index(['unit_id', 'billing_period']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            // What the line is for; decides the revenue account (config accounting.invoice_item_accounts).
            $table->string('category', 30)->nullable()->after('description'); // water|rent|service_charge|other
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['unit_id', 'billing_period']);
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('billing_period');
        });

        Schema::table('meters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::dropIfExists('units');
        Schema::dropIfExists('properties');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
