<?php

use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Projects\ExpenseWorkflow;
use App\Services\Projects\MaterialLedger;
use App\Services\Projects\ProjectFinancials;
use App\Services\Projects\ProjectLifecycle;
use Database\Seeders\DemoProjectSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->vendor = Vendor::factory()->create();
    $this->owner = User::factory()->create(['vendor_id' => $this->vendor->id]);
    $this->owner->assignRole('vendor');
    $this->actingAs($this->owner);

    $this->project = Project::create([
        'vendor_id' => $this->vendor->id, 'reference' => 'BH-2026-001', 'name' => 'Test borehole', 'status' => 'drilling',
        'original_budget' => '100000.00', 'current_budget' => '100000.00', 'contingency' => '5000.00',
    ]);
    $this->supplier = Supplier::create(['vendor_id' => $this->vendor->id, 'name' => 'Pipes Ltd']);
});

/** Materials, expenses, labour, equipment and tasks for one project — see expected figures below. */
function buildCostScenario(Project $project, Supplier $supplier): void
{
    $ledger = app(MaterialLedger::class);
    $casing = $project->materials()->create(['name' => 'Casing', 'unit' => 'pcs', 'reorder_level' => '0']);

    $ledger->record($casing, ['type' => 'ordered', 'quantity' => '30', 'unit_cost' => '1000', 'supplier_id' => $supplier->id, 'movement_date' => today()->toDateString()]);
    $ledger->record($casing, ['type' => 'received', 'quantity' => '10', 'unit_cost' => '1000', 'movement_date' => today()->toDateString()]);
    $ledger->record($casing, ['type' => 'received', 'quantity' => '10', 'unit_cost' => '1200', 'movement_date' => today()->toDateString()]);
    $ledger->record($casing, ['type' => 'consumed', 'quantity' => '12', 'movement_date' => today()->toDateString()]);
    $ledger->record($casing, ['type' => 'wasted', 'quantity' => '1', 'movement_date' => today()->toDateString()]);
    $ledger->record($casing, ['type' => 'returned', 'quantity' => '2', 'movement_date' => today()->toDateString()]);

    $workflow = app(ExpenseWorkflow::class);
    $base = ['expense_date' => today()->toDateString(), 'supplier_id' => null, 'payee_name' => 'Driver'];

    $workflow->approve($workflow->submit($project, $base + ['category' => 'fuel_transport', 'description' => 'Diesel', 'amount' => '5000.00']));

    // The supplier's bill for the casing: cash only — the casing is already costed through consumption.
    $bill = $workflow->submit($project, ['category' => 'materials', 'description' => 'Casing invoice', 'amount' => '22000.00',
        'expense_date' => today()->toDateString(), 'supplier_id' => $supplier->id, 'reference' => 'INV-77', 'is_settlement' => true]);
    $workflow->approve($bill);
    $workflow->recordPayment($bill, '10000.00');

    $workflow->submit($project, $base + ['category' => 'other', 'description' => 'Pending item', 'amount' => '3000.00']);
    $workflow->reject($workflow->submit($project, $base + ['category' => 'other', 'description' => 'Rejected item', 'amount' => '7000.00']), 'Not ours');

    $project->assignments()->create(['kind' => 'labour', 'name' => 'Helper', 'rate' => '1500.00', 'rate_unit' => 'day', 'units_worked' => '4', 'extra_cost' => '500.00']);
    $project->assignments()->create(['kind' => 'equipment', 'name' => 'Rig', 'rate' => '20000.00', 'rate_unit' => 'day', 'units_worked' => '2', 'extra_cost' => '0']);

    $project->tasks()->create(['title' => 'Install pump', 'status' => 'todo', 'estimated_cost' => '4000.00']);
    $project->tasks()->create(['title' => 'Survey', 'status' => 'done', 'estimated_cost' => '9999.00']);
}

test('project cost rolls up materials, expenses, labour and equipment without double counting', function () {
    buildCostScenario($this->project, $this->supplier);

    $f = app(ProjectFinancials::class)->summary($this->project->fresh());

    // Casing: 20 received at an average of 1,100; 12 consumed + 1 wasted are cost, 5 left on hand.
    expect($f['material_cost'])->toBe('14300.00')
        ->and($f['wastage_cost'])->toBe('1100.00')
        ->and($f['stock_value'])->toBe('5500.00')
        ->and($f['materials_purchased'])->toBe('22000.00')
        ->and($f['committed'])->toBe('10000.00')
        // Only the approved, non-settlement diesel is direct cost.
        ->and($f['direct_expenses'])->toBe('5000.00')
        ->and($f['labour_cost'])->toBe('6500.00')
        ->and($f['equipment_cost'])->toBe('40000.00')
        ->and($f['actual_cost'])->toBe('65800.00')
        // Open task estimate + open purchase orders.
        ->and($f['estimate_to_complete'])->toBe('14000.00')
        ->and($f['forecast_final'])->toBe('79800.00')
        ->and($f['variance'])->toBe('20200.00')
        ->and($f['remaining_budget'])->toBe('34200.00')
        ->and($f['utilization'])->toBe(65.8)
        // Cash is separate from cost: the settlement bill counts here.
        ->and($f['expenses_approved'])->toBe('27000.00')
        ->and($f['paid'])->toBe('10000.00')
        ->and($f['unpaid_approved'])->toBe('17000.00')
        ->and($f['expenses_pending'])->toBe('3000.00');
});

test('a manual estimate to complete overrides the task-based estimate', function () {
    buildCostScenario($this->project, $this->supplier);
    $this->project->update(['estimated_cost_to_complete' => '1000.00']);

    $f = app(ProjectFinancials::class)->summary($this->project->fresh());

    expect($f['forecast_final'])->toBe('66800.00')
        ->and($f['variance'])->toBe('33200.00');
});

test('stock can never be consumed, returned or wasted twice', function () {
    $ledger = app(MaterialLedger::class);
    $gravel = $this->project->materials()->create(['name' => 'Gravel', 'unit' => 'bags']);
    $ledger->record($gravel, ['type' => 'received', 'quantity' => '5', 'unit_cost' => '300', 'movement_date' => today()->toDateString()]);
    $ledger->record($gravel, ['type' => 'consumed', 'quantity' => '5', 'movement_date' => today()->toDateString()]);

    expect(fn () => $ledger->record($gravel, ['type' => 'consumed', 'quantity' => '1', 'movement_date' => today()->toDateString()]))
        ->toThrow(ValidationException::class);

    expect($ledger->summary($gravel->fresh())['on_hand'])->toBe('0.000')
        ->and($ledger->summary($gravel->fresh())['cost'])->toBe('1500.00');
});

test('a duplicate expense is refused', function () {
    $workflow = app(ExpenseWorkflow::class);
    $data = ['category' => 'permits', 'description' => 'Drilling permit', 'amount' => '2500.00', 'expense_date' => today()->toDateString(), 'reference' => 'WRA-1'];

    $workflow->submit($this->project, $data);

    expect(fn () => $workflow->submit($this->project, $data))->toThrow(ValidationException::class)
        ->and($this->project->expenses()->count())->toBe(1);
});

test('payments cannot exceed what is owed and only approved expenses can be paid', function () {
    $workflow = app(ExpenseWorkflow::class);
    $expense = $workflow->submit($this->project, ['category' => 'drilling', 'description' => 'Drilling', 'amount' => '1000.00', 'expense_date' => today()->toDateString(), 'payee_name' => 'Crew']);

    expect(fn () => $workflow->recordPayment($expense, '100.00'))->toThrow(ValidationException::class);

    $workflow->approve($expense);
    $workflow->recordPayment($expense->fresh(), '400.00');

    expect(fn () => $workflow->recordPayment($expense->fresh(), '700.00'))->toThrow(ValidationException::class)
        ->and($expense->fresh()->paymentStatus())->toBe('partial');
});

test('crossing a budget threshold raises one alert and a budget revision keeps the original', function () {
    $workflow = app(ExpenseWorkflow::class);
    $workflow->approve($workflow->submit($this->project, ['category' => 'drilling', 'description' => 'Drilling', 'amount' => '80000.00', 'expense_date' => today()->toDateString(), 'payee_name' => 'Crew']));

    expect($this->project->fresh()->budget_alert_level)->toBe(75)
        ->and($this->project->activities()->where('event', 'budget_alert')->count())->toBe(1);

    app(ProjectLifecycle::class)->reviseBudget($this->project->fresh(), '200000', 'Deeper aquifer');

    $project = $this->project->fresh();
    expect((string) $project->original_budget)->toBe('100000.00')
        ->and((string) $project->current_budget)->toBe('200000.00')
        ->and($project->budget_alert_level)->toBe(0)
        ->and($project->activities()->where('event', 'budget_revised')->exists())->toBeTrue();
});

test('the demo project seeds with totals that reconcile', function () {
    $demo = Vendor::factory()->create(['slug' => 'demo-water']);
    $owner = User::factory()->create(['vendor_id' => $demo->id, 'email' => 'vendor@swb.test']);
    $owner->assignRole('vendor');

    $this->seed(DemoProjectSeeder::class);
    $this->seed(DemoProjectSeeder::class); // idempotent

    $project = Project::withoutGlobalScope('vendor')->where('vendor_id', $demo->id)->sole();
    $f = app(ProjectFinancials::class)->summary($project);

    expect($f['material_cost'])->toBe('110000.00')
        ->and($f['actual_cost'])->toBe('341000.00')
        ->and($f['expenses_approved'])->toBe('138000.00')
        ->and($f['paid'])->toBe('78000.00')
        ->and($f['expenses_pending'])->toBe('15000.00');
});

test('stage transitions follow the configured lifecycle and are logged', function () {
    $lifecycle = app(ProjectLifecycle::class);

    expect(fn () => $lifecycle->transition($this->project, 'completed'))->toThrow(ValidationException::class);

    $lifecycle->transition($this->project, 'casing_installation', 'Target depth reached');

    expect($this->project->fresh()->status)->toBe('casing_installation')
        ->and($this->project->activities()->where('event', 'status_changed')->first()->properties)
        ->toMatchArray(['from' => 'drilling', 'to' => 'casing_installation']);
});
