<?php

namespace App\Exports\Projects;

use App\Models\Project;
use App\Services\Projects\MaterialLedger;
use App\Services\Projects\ProjectFinancials;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/** One project's cost statement: summary, expenditure ledger, materials, labour & equipment. */
class ProjectLedgerExport implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(protected Project $project) {}

    public function array(): array
    {
        $p = $this->project->load(['expenses.supplier', 'materials.movements', 'assignments']);
        $f = app(ProjectFinancials::class)->summary($p);
        $ledger = app(MaterialLedger::class);

        $rows = [
            ["{$p->reference} — {$p->name}"],
            ['Stage', $p->stageLabel()],
            [],
            ['Budget vs actual'],
            ['Original budget', $f['original_budget']],
            ['Approved variations', $f['variations']],
            ['Current budget', $f['current_budget']],
            ['Materials consumed & wasted', $f['material_cost']],
            ['Labour', $f['labour_cost']],
            ['Equipment', $f['equipment_cost']],
            ['Other direct expenses', $f['direct_expenses']],
            ['Actual cost', $f['actual_cost']],
            ['Committed (open POs)', $f['committed']],
            ['Estimate to complete', $f['estimate_to_complete']],
            ['Forecast final cost', $f['forecast_final']],
            ['Variance (budget − forecast)', $f['variance']],
            ['Utilisation %', $f['utilization']],
            [],
            ['Expenditure ledger'],
            ['Date', 'Category', 'Description', 'Payee', 'Status', 'Counts as cost', 'Amount', 'Paid', 'Outstanding'],
        ];

        foreach ($p->expenses->sortBy('expense_date') as $e) {
            $rows[] = [$e->expense_date->toDateString(), $e->categoryLabel(), $e->description, $e->payee(), $e->status,
                $e->is_settlement ? 'No (settlement)' : 'Yes', (string) $e->amount, (string) $e->paid_amount, $e->outstanding()];
        }

        $rows[] = [];
        $rows[] = ['Materials'];
        $rows[] = ['Material', 'Unit', 'Received', 'Consumed', 'Returned', 'Wasted', 'On hand', 'Avg cost', 'Cost', 'Wastage cost'];
        foreach ($p->materials as $m) {
            $s = $ledger->summary($m);
            $rows[] = [$m->name, $m->unit, $s['received'], $s['consumed'], $s['returned'], $s['wasted'], $s['on_hand'], $s['avg_cost'], $s['cost'], $s['wastage_cost']];
        }

        $rows[] = [];
        $rows[] = ['Labour & equipment'];
        $rows[] = ['Type', 'Name', 'Role', 'Rate', 'Per', 'Units', 'Extra', 'Cost'];
        foreach ($p->assignments as $a) {
            $rows[] = [$a->kind, $a->name, $a->role, (string) $a->rate, $a->rate_unit, (string) $a->units_worked, (string) $a->extra_cost, $a->cost()];
        }

        return $rows;
    }

    public function title(): string
    {
        return $this->project->reference;
    }
}
