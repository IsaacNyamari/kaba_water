<?php

namespace App\Exports\Accounting;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Base for statement exports built from ReportService arrays.
 * Amounts stay decimal strings ("1234.50"); the spreadsheet stores them as numbers.
 */
abstract class StatementExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $report */
    public function __construct(protected array $report) {}

    /** @return list<array<int, string|null>> */
    protected function section(string $title, array $rows, string $totalLabel, string $total): array
    {
        $out = [[$title, null, null]];
        foreach ($rows as $r) {
            $out[] = [$r['code'], $r['name'], $r['amount']];
        }
        $out[] = [null, $totalLabel, $total];
        $out[] = [null, null, null];

        return $out;
    }
}
