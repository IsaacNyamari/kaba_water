<?php

namespace App\Exports\Accounting;

class TrialBalanceExport extends StatementExport
{
    public function title(): string
    {
        return 'Trial Balance';
    }

    public function headings(): array
    {
        return ['Account code', 'Account name', 'Type', 'Debit', 'Credit'];
    }

    public function array(): array
    {
        $rows = array_map(fn ($r) => [$r['code'], $r['name'], $r['type'], $r['debit'], $r['credit']], $this->report['rows']);
        $rows[] = [null, 'TOTAL', null, $this->report['total_debit'], $this->report['total_credit']];

        return $rows;
    }
}
