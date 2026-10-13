<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->string('type');         // asset|liability|equity|revenue|expense (App\Enums\AccountType)
            $table->string('normal_side');  // debit|credit (App\Enums\NormalSide)
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // seeded accounts can't be deleted or re-coded
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vendor_id', 'code']);
            $table->index(['vendor_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
