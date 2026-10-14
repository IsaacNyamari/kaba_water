<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Support\Money\MoneyMath;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Server-side cost roll-up for a project (internal cost only — no revenue).
 *
 *  Actual cost      = materials consumed/wasted + approved direct expenses + labour + equipment
 *                     (settlement expenses and stock still on hand are never cost)
 *  Est. to complete = manual estimate, or open tasks' estimated cost + committed purchase orders
 *  Forecast final   = Actual + Est. to complete
 *  Variance         = Current budget − Forecast final
 *  Utilisation %    = Actual ÷ Current budget × 100
 *
 * Cash is kept apart from cost: "paid" and "outstanding" come from approved
 * bills (including settlements), not from what was consumed.
 */
class ProjectFinancials
{
    public function __construct(protected MaterialLedger $ledger) {}

    /** @return array<string, mixed> */
    public function summary(Project $project): array
    {
        $project->loadMissing(['materials.movements', 'expenses', 'assignments', 'tasks']);

        // ── Materials ────────────────────────────────────────
        $materialCost = $wastage = $stockValue = $committed = $purchased = '0.00';
        foreach ($project->materials as $material) {
            $s = $this->ledger->summary($material);
            $materialCost = MoneyMath::add($materialCost, $s['cost']);
            $wastage = MoneyMath::add($wastage, $s['wastage_cost']);
            $stockValue = MoneyMath::add($stockValue, $s['stock_value']);
            $committed = MoneyMath::add($committed, $s['committed']);
            $purchased = MoneyMath::add($purchased, $s['purchased_value']);
        }

        // ── Expenses ─────────────────────────────────────────
        $approved = $project->expenses->where('status', 'approved');
        $directCost = MoneyMath::sumColumn($approved->where('is_settlement', false), 'amount');
        $byCategory = $approved->where('is_settlement', false)->groupBy('category')
            ->map(fn ($rows) => MoneyMath::sumColumn($rows, 'amount'))->sort(fn ($a, $b) => MoneyMath::compare($b, $a))->all();
        $byStage = $approved->where('is_settlement', false)->groupBy(fn ($e) => $e->stage ?: 'unassigned')
            ->map(fn ($rows) => MoneyMath::sumColumn($rows, 'amount'))->all();

        $submitted = MoneyMath::sumColumn($project->expenses->where('status', '!=', 'rejected'), 'amount');
        $approvedTotal = MoneyMath::sumColumn($approved, 'amount');
        $paid = MoneyMath::sumColumn($approved, 'paid_amount');
        $outstanding = MoneyMath::sub($approvedTotal, $paid);
        $pendingApproval = MoneyMath::sumColumn($project->expenses->where('status', 'submitted'), 'amount');
        $contingencyUsed = MoneyMath::sumColumn($approved->where('category', 'contingency'), 'amount');

        // ── Labour & equipment ───────────────────────────────
        $labour = '0.00';
        $equipment = '0.00';
        foreach ($project->assignments as $a) {
            if ($a->kind === 'equipment') {
                $equipment = MoneyMath::add($equipment, $a->cost());
            } else {
                $labour = MoneyMath::add($labour, $a->cost());
            }
        }

        // ── Roll-up ──────────────────────────────────────────
        $actual = MoneyMath::add($materialCost, $directCost, $labour, $equipment);
        $openTaskEstimate = MoneyMath::sumColumn($project->tasks->where('status', '!=', 'done'), 'estimated_cost');
        $etc = $project->estimated_cost_to_complete !== null
            ? MoneyMath::str((string) $project->estimated_cost_to_complete)
            : MoneyMath::add($openTaskEstimate, $committed);
        $forecast = MoneyMath::add($actual, $etc);
        $budget = MoneyMath::str((string) $project->current_budget);

        return [
            'original_budget' => MoneyMath::str((string) $project->original_budget),
            'current_budget' => $budget,
            'variations' => MoneyMath::sub($budget, (string) $project->original_budget),
            'contingency' => MoneyMath::str((string) $project->contingency),
            'contingency_remaining' => MoneyMath::sub((string) $project->contingency, $contingencyUsed),

            'material_cost' => $materialCost,
            'wastage_cost' => $wastage,
            'materials_purchased' => $purchased,
            'stock_value' => $stockValue,
            'direct_expenses' => $directCost,
            'labour_cost' => $labour,
            'equipment_cost' => $equipment,

            'actual_cost' => $actual,
            'committed' => $committed,
            'estimate_to_complete' => $etc,
            'forecast_final' => $forecast,
            'remaining_budget' => MoneyMath::sub($budget, $actual),
            'variance' => MoneyMath::sub($budget, $forecast),
            'utilization' => $this->percent($actual, $budget),

            'expenses_submitted' => $submitted,
            'expenses_approved' => $approvedTotal,
            'expenses_pending' => $pendingApproval,
            'paid' => $paid,
            'unpaid_approved' => $outstanding,
            'by_category' => $byCategory,
            'by_stage' => $byStage,
        ];
    }

    /** a ÷ b × 100, one decimal place; 0 when there is no budget. */
    public function percent(string $part, string $whole): float
    {
        if (! MoneyMath::isPositive($whole)) {
            return 0.0;
        }

        return (float) (string) BigDecimal::of($part)->multipliedBy(100)->dividedBy($whole, 1, RoundingMode::HalfUp);
    }
}
