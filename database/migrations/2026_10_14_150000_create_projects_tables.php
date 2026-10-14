<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borehole Project Management module — internal cost and progress tracking.
 * No client / revenue concepts: projects are tracked against an internal budget.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');

            // Location
            $table->string('county', 100)->nullable();
            $table->string('sub_county', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('site_details')->nullable();

            // Schedule
            $table->date('start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();

            // Budget (internal cost budget — no revenue)
            $table->decimal('original_budget', 15, 2)->default(0);
            $table->decimal('current_budget', 15, 2)->default(0);
            $table->decimal('contingency', 15, 2)->default(0);
            $table->decimal('estimated_cost_to_complete', 15, 2)->nullable();
            $table->unsignedTinyInteger('budget_alert_level')->default(0);

            // Borehole specification
            $table->decimal('planned_depth_m', 8, 2)->nullable();
            $table->decimal('actual_depth_m', 8, 2)->nullable();
            $table->unsignedInteger('diameter_mm')->nullable();
            $table->string('casing_requirements')->nullable();
            $table->string('pump_capacity')->nullable();
            $table->text('scope')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('special_requirements')->nullable();

            $table->json('technical')->nullable();
            $table->json('attachments')->nullable();

            // Closure
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('final_statement')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vendor_id', 'reference']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->boolean('is_mandatory')->default(true);
            $table->date('planned_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_waived')->default(false);
            $table->string('waiver_reason')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('depends_on_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('todo');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->string('blocker')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('project_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 30);
            $table->text('activities_completed');
            $table->text('next_day_plan')->nullable();
            $table->decimal('planned_depth_m', 8, 2)->nullable();
            $table->decimal('actual_depth_m', 8, 2)->nullable();
            $table->text('labour_summary')->nullable();
            $table->text('equipment_summary')->nullable();
            $table->string('weather', 100)->nullable();
            $table->text('challenges')->nullable();
            $table->text('incidents')->nullable();
            $table->text('risks')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->text('pm_remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // One report per project per day — a second submission edits the first.
            $table->unique(['project_id', 'report_date']);
        });

        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('description');
            $table->text('justification')->nullable();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('KES');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payee_name')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('stage', 30)->nullable();
            // True when this bill pays for materials / labour / equipment already
            // costed elsewhere in the project — counted for cash, never as cost again.
            $table->boolean('is_settlement')->default(false);
            $table->string('status', 20)->default('submitted');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('receipt_path')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'category']);
        });

        Schema::create('project_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 60)->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->string('storage_location')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::create('project_material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_material_id')->constrained()->cascadeOnDelete();
            // ordered | received | consumed | returned | wasted
            $table->string('type', 20);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 100)->nullable();
            $table->date('movement_date');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['project_material_id', 'type']);
        });

        Schema::create('project_team_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('labour'); // labour | equipment
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('rate', 15, 2)->default(0);
            $table->string('rate_unit', 10)->default('day'); // day | hour
            $table->decimal('units_worked', 10, 2)->default(0);
            $table->decimal('extra_cost', 15, 2)->default(0); // overtime, allowances, fuel, repairs
            $table->decimal('downtime_units', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'kind']);
        });

        Schema::create('project_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('description');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_activity_log');
        Schema::dropIfExists('project_team_assignments');
        Schema::dropIfExists('project_material_movements');
        Schema::dropIfExists('project_materials');
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('project_progress_reports');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
    }
};
