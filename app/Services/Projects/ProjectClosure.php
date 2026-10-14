<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Support\Money\MoneyMath;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closure and final cost reconciliation. A project closes only when every
 * checklist item passes; the final statement is frozen on the project and
 * reopening is a logged, permissioned action. Technical completion (drilling
 * done) is not closure — closure also needs the money and paperwork settled.
 */
class ProjectClosure
{
    /** Technical fields that must be recorded before closure. */
    public const REQUIRED_TECHNICAL = ['final_depth_m', 'yield_m3_per_hour', 'water_quality_result', 'final_inspection_date', 'final_inspection_result'];

    public function __construct(protected ProjectFinancials $financials, protected MaterialLedger $ledger) {}

    /** @return list<array{key: string, label: string, passed: bool, detail: string}> */
    public function checklist(Project $project): array
    {
        $project->loadMissing(['milestones', 'materials.movements', 'assignments', 'expenses']);
        $technical = $project->technical ?? [];
        $attachments = collect($project->attachments ?? []);

        $openMilestones = $project->milestones->where('is_mandatory', true)->reject->isSettled();
        $missingTechnical = collect(self::REQUIRED_TECHNICAL)->filter(fn ($k) => blank($technical[$k] ?? null));

        $unreconciled = $project->materials->filter(fn ($m) => BigDecimal::of($this->ledger->summary($m)['on_hand'])->isPositive());
        $openOrders = $project->materials->filter(fn ($m) => MoneyMath::isPositive($this->ledger->summary($m)['committed']));
        $openTimesheets = $project->assignments->whereNull('end_date');
        $pendingExpenses = $project->expenses->where('status', 'submitted');
        $unpaid = $project->expenses->where('status', 'approved')->filter(fn ($e) => $e->paymentStatus() !== 'paid');
        $openDefects = collect($technical['defects'] ?? [])->where('resolved', false);
        $handover = $attachments->whereIn('type', ['handover', 'completion_certificate']);

        $item = fn (string $key, string $label, bool $passed, string $detail) => compact('key', 'label', 'passed', 'detail');

        return [
            $item('milestones', 'Mandatory milestones complete or waived', $openMilestones->isEmpty(),
                $openMilestones->isEmpty() ? 'All done.' : $openMilestones->count().' open: '.$openMilestones->pluck('name')->take(3)->implode(', ')),
            $item('technical', 'Final drilling & technical records', $missingTechnical->isEmpty(),
                $missingTechnical->isEmpty() ? 'Recorded.' : 'Missing: '.$missingTechnical->map(fn ($k) => str_replace('_', ' ', $k))->implode(', ')),
            $item('materials', 'Materials reconciled (no unexplained stock)', $unreconciled->isEmpty(),
                $unreconciled->isEmpty() ? 'All stock used, returned or written off.' : 'Still in stock: '.$unreconciled->pluck('name')->implode(', ')),
            $item('orders', 'Outstanding purchase orders resolved', $openOrders->isEmpty(),
                $openOrders->isEmpty() ? 'Nothing awaiting delivery.' : 'Awaiting delivery: '.$openOrders->pluck('name')->implode(', ')),
            $item('timesheets', 'Labour & equipment timesheets finalised', $openTimesheets->isEmpty(),
                $openTimesheets->isEmpty() ? 'All have an end date.' : $openTimesheets->count().' without an end date'),
            $item('expenses', 'Expenses approved or rejected', $pendingExpenses->isEmpty(),
                $pendingExpenses->isEmpty() ? 'None awaiting approval.' : $pendingExpenses->count().' awaiting approval'),
            $item('payables', 'Supplier invoices settled', $unpaid->isEmpty(),
                $unpaid->isEmpty() ? 'All approved bills paid.' : $unpaid->count().' unpaid ('.MoneyMath::format(MoneyMath::sumColumn($unpaid->map(fn ($e) => ['o' => $e->outstanding()]), 'o')).')'),
            $item('defects', 'Defects resolved or documented', $openDefects->isEmpty(),
                $openDefects->isEmpty() ? 'No open defects.' : $openDefects->count().' open defect(s)'),
            $item('handover', 'Handover documents & completion certificate attached', $handover->isNotEmpty(),
                $handover->isNotEmpty() ? $handover->count().' document(s).' : 'Upload a handover document or completion certificate.'),
        ];
    }

    public function canClose(Project $project): bool
    {
        return collect($this->checklist($project))->every('passed');
    }

    /** Frozen final cost & technical statement. */
    public function statement(Project $project): array
    {
        $f = $this->financials->summary($project->fresh());
        $actual = $f['actual_cost'];

        return [
            'generated_at' => now()->toIso8601String(),
            'original_budget' => $f['original_budget'],
            'approved_variations' => $f['variations'],
            'current_budget' => $f['current_budget'],
            'material_cost' => $f['material_cost'],
            'wastage_cost' => $f['wastage_cost'],
            'labour_cost' => $f['labour_cost'],
            'equipment_cost' => $f['equipment_cost'],
            'other_costs' => $f['direct_expenses'],
            'total_final_cost' => $actual,
            'variance' => MoneyMath::sub($f['current_budget'], $actual),
            'paid' => $f['paid'],
            'by_category' => $f['by_category'],
            'technical' => $project->technical ?? [],
            'planned_depth_m' => (string) $project->planned_depth_m,
            'actual_depth_m' => (string) $project->actual_depth_m,
        ];
    }

    public function close(Project $project, ?string $notes = null): void
    {
        if ($project->isClosed()) {
            throw ValidationException::withMessages(['close' => 'This project is already closed.']);
        }

        if (! $this->canClose($project)) {
            throw ValidationException::withMessages(['close' => 'Finish every item on the closure checklist first.']);
        }

        DB::transaction(function () use ($project, $notes) {
            $statement = $this->statement($project);
            $from = $project->status;

            $project->update([
                'status' => 'completed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                'actual_completion_date' => $project->actual_completion_date ?? today(),
                'final_statement' => $statement + ['notes' => $notes],
            ]);

            $project->log('closed', 'Project closed and handed over. Final cost '.MoneyMath::format($statement['total_final_cost']), [
                'from' => $from, 'statement' => $statement, 'notes' => $notes,
            ]);
        });
    }

    public function reopen(Project $project, string $reason): void
    {
        if ($project->status !== 'completed') {
            throw ValidationException::withMessages(['reopen' => 'Only a closed project can be reopened.']);
        }

        DB::transaction(function () use ($project, $reason) {
            // The previous statement is kept in the activity log for audit.
            $project->log('reopened', "Project reopened — {$reason}", ['reason' => $reason, 'previous_statement' => $project->final_statement]);

            $project->update(['status' => 'commissioning', 'closed_at' => null, 'closed_by' => null]);
        });
    }
}
