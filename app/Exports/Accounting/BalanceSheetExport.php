<?php

namespace App\Exports\Accounting;

class BalanceSheetExport extends StatementExport
{
    public function title(): string
    {
        return 'Balance Sheet';
    }

    public function headings(): array
    {
        return ['Account code', 'Account name', 'Balance'];
    }

    public function array(): array
    {
        $r = $this->report;

        return array_merge(
            $this->section('Assets', $r['assets'], 'Total assets', $r['total_assets']),
            $this->section('Liabilities', $r['liabilities'], 'Total liabilities', $r['total_liabilities']),
            $this->section('Equity', $r['equity'], 'Total equity', $r['total_equity']),
            [[null, 'TOTAL LIABILITIES + EQUITY', $r['total_liabilities_and_equity']]],
        );
    }
}
