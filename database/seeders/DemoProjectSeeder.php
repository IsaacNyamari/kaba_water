<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectSupplier;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Projects\ExpenseWorkflow;
use App\Services\Projects\MaterialLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * One realistic borehole project for the demo vendor, mid-drilling, so the
 * Project Management module isn't empty on first install. Idempotent.
 *
 * Expected roll-up (no double counting):
 *   materials consumed/wasted 110,000 + diesel 18,000 + labour 33,000 + rig 180,000 = 341,000 actual cost
 *   The 120,000 casing bill is a settlement: it shows in payables/paid, not again as cost.
 */
class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $vendor = Vendor::where('slug', 'demo-water')->first();
        $owner = User::where('email', 'vendor@swb.test')->first();

        if (! $vendor || ! $owner || Project::withoutGlobalScope('vendor')->where('vendor_id', $vendor->id)->exists()) {
            return;
        }

        Auth::login($owner);

        $start = today()->subDays(20);
        $project = Project::create([
            'vendor_id' => $vendor->id,
            'reference' => Project::nextReference($vendor->id),
            'name' => 'Kitengela community borehole',
            'description' => 'Production borehole for the Kitengela estate, with a 5 m³/h submersible pump and a 10 m³ elevated tank.',
            'status' => 'drilling',
            'county' => 'Kajiado',
            'sub_county' => 'Kajiado East',
            'latitude' => '-1.4733000',
            'longitude' => '36.9601000',
            'start_date' => $start,
            'expected_completion_date' => $start->addDays(60),
            'project_manager_id' => $owner->id,
            'original_budget' => '1200000.00',
            'current_budget' => '1200000.00',
            'contingency' => '100000.00',
            'planned_depth_m' => '180',
            'diameter_mm' => 200,
            'casing_requirements' => '6" uPVC casing and screens, 180 m',
            'pump_capacity' => '5 m³/h submersible',
        ]);

        foreach (config('projects.milestones') as $i => [$name, $weight]) {
            $project->milestones()->create([
                'name' => $name, 'weight' => $weight, 'sort_order' => $i + 1,
                'planned_date' => $start->addDays((int) round(60 * ($i + 1) / 13)),
                'completed_at' => $i < 4 ? $start->addDays($i * 4) : null,
                'completed_by' => $i < 4 ? $owner->id : null,
            ]);
        }
        $project->log('created', "Project {$project->reference} registered (demo data)");

        $supplier = ProjectSupplier::firstOrCreate(['vendor_id' => $vendor->id, 'name' => 'Davis & Shirtliff Kitengela'], ['phone' => '+254700111222', 'category' => 'materials']);
        $ledger = app(MaterialLedger::class);
        $casing = $project->materials()->create(['name' => '6" uPVC casing (6 m)', 'sku' => 'CAS-6', 'unit' => 'lengths', 'reorder_level' => '5']);
        $ledger->record($casing, ['type' => 'ordered', 'quantity' => '30', 'unit_cost' => '4000', 'supplier_id' => $supplier->id, 'reference' => 'PO-001', 'movement_date' => $start->addDays(2)->toDateString()]);
        $ledger->record($casing, ['type' => 'received', 'quantity' => '30', 'unit_cost' => '4000', 'supplier_id' => $supplier->id, 'reference' => 'DN-5512', 'movement_date' => $start->addDays(5)->toDateString()]);
        $ledger->record($casing, ['type' => 'consumed', 'quantity' => '22', 'movement_date' => $start->addDays(15)->toDateString()]);
        $ledger->record($casing, ['type' => 'wasted', 'quantity' => '1', 'notes' => 'Cracked on offloading', 'movement_date' => $start->addDays(15)->toDateString()]);

        $gravel = $project->materials()->create(['name' => 'Gravel pack', 'unit' => 'tonnes', 'reorder_level' => '2']);
        $ledger->record($gravel, ['type' => 'received', 'quantity' => '6', 'unit_cost' => '3000', 'movement_date' => $start->addDays(6)->toDateString()]);
        $ledger->record($gravel, ['type' => 'consumed', 'quantity' => '6', 'movement_date' => $start->addDays(16)->toDateString()]);

        $workflow = app(ExpenseWorkflow::class);
        $workflow->approve($workflow->submit($project, ['category' => 'fuel_transport', 'description' => 'Diesel for rig and compressor', 'amount' => '18000.00',
            'expense_date' => $start->addDays(10)->toDateString(), 'payee_name' => 'Rubis Kitengela', 'payment_method' => 'mpesa']));
        $fuel = $project->expenses()->latest('id')->first();
        $workflow->recordPayment($fuel, '18000.00', 'mpesa');

        $bill = $workflow->submit($project, ['category' => 'materials', 'description' => 'Casing supply invoice', 'amount' => '120000.00', 'is_settlement' => true,
            'expense_date' => $start->addDays(5)->toDateString(), 'supplier_id' => $supplier->id, 'reference' => 'INV-5512']);
        $workflow->approve($bill);
        $workflow->recordPayment($bill->fresh(), '60000.00', 'bank');

        $workflow->submit($project, ['category' => 'permits', 'description' => 'WRA drilling permit', 'amount' => '15000.00',
            'expense_date' => $start->toDateString(), 'payee_name' => 'Water Resources Authority', 'reference' => 'WRA-2026-118']);

        $project->assignments()->create(['kind' => 'labour', 'name' => 'Drilling crew (4)', 'role' => 'Driller & helpers', 'start_date' => $start->addDays(8), 'rate' => '3000.00', 'rate_unit' => 'day', 'units_worked' => '10', 'extra_cost' => '3000.00']);
        $project->assignments()->create(['kind' => 'equipment', 'name' => 'Rotary drilling rig (hired)', 'role' => 'Rig', 'start_date' => $start->addDays(8), 'rate' => '18000.00', 'rate_unit' => 'day', 'units_worked' => '10', 'downtime_units' => '1']);

        $project->tasks()->create(['title' => 'Reach target depth (180 m)', 'status' => 'in_progress', 'progress' => 70, 'assigned_to' => $owner->id, 'start_date' => $start->addDays(8), 'due_date' => today()->addDays(4), 'estimated_cost' => '90000.00']);
        $project->tasks()->create(['title' => 'Pump installation', 'status' => 'todo', 'start_date' => today()->addDays(15), 'due_date' => today()->addDays(22), 'estimated_cost' => '250000.00']);

        $project->progressReports()->create([
            'report_date' => today()->subDay(), 'reported_by' => $owner->id, 'stage' => 'drilling',
            'activities_completed' => 'Drilled from 112 m to 126 m in weathered basalt. Installed 4 casing lengths.',
            'next_day_plan' => 'Continue drilling to 140 m.', 'planned_depth_m' => '130', 'actual_depth_m' => '126',
            'weather' => 'Sunny', 'progress_percent' => 40,
        ]);
        $project->update(['actual_depth_m' => '126']);

        Auth::logout();
    }
}
