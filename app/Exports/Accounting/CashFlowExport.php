<?php

namespace App\Exports\Accounting;

class CashFlowExport extends StatementExport
{
    public function title(): string
    {
        return 'Cash Flow';
    }

    public function headings(): array
    {
        return ['Account code', 'Line', 'Amount'];
    }

    public function array(): array
    {
        $r = $this->report;

        $operating = [['Operating activities', null, null], [null, 'Net income', $r['net_income']]];
        foreach (array_merge($r['adjustments'], $r['working_capital']) as $row) {
            $operating[] = [$row['code'], $row['name'], $row['amount']];
        }
        $operating[] = [null, 'Net cash from operating activities', $r['operating']];
        $operating[] = [null, null, null];

        return array_merge(
            $operating,
            $this->section('Investing activities', $r['investing'], 'Net cash from investing activities', $r['investing_total']),
            $this->section('Financing activities', $r['financing'], 'Net cash from financing activities', $r['financing_total']),
            [
                [null, 'Net change in cash', $r['net_change']],
                [null, 'Opening cash', $r['opening_cash']],
                [null, 'Closing cash', $r['closing_cash']],
            ],
        );
    }
}
