<?php

namespace App\Exports\Accounting;

class GeneralLedgerExport extends StatementExport
{
    public function title(): string
    {
        return 'General Ledger';
    }

    public function headings(): array
    {
        return ['Date', 'Entry #', 'Reference', 'Description', 'Debit', 'Credit', 'Balance'];
    }

    public function array(): array
    {
        $r = $this->report;
        $a = $r['account'];

        $rows = [[null, null, "{$a->code} {$a->name}", 'Opening balance', null, null, $r['opening']]];
        foreach ($r['rows'] as $l) {
            $rows[] = [$l['date'], $l['entry_id'], $l['reference'], $l['description'], $l['debit'], $l['credit'], $l['balance']];
        }
        $rows[] = [null, null, null, 'TOTAL / CLOSING', $r['total_debit'], $r['total_credit'], $r['closing']];

        return $rows;
    }
}
