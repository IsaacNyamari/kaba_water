<?php

namespace App\Exports\Accounting;

class IncomeStatementExport extends StatementExport
{
    public function title(): string
    {
        return 'Income Statement';
    }

    public function headings(): array
    {
        return ['Account code', 'Account name', 'Amount'];
    }

    public function array(): array
    {
        $r = $this->report;

        return array_merge(
            $this->section('Revenue', $r['revenue'], 'Total revenue', $r['total_revenue']),
            $this->section('Expenses', $r['expenses'], 'Total expenses', $r['total_expenses']),
            [[null, 'NET INCOME', $r['net_income']]],
        );
    }
}
