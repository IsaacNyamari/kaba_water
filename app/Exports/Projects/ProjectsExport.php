<?php

namespace App\Exports\Projects;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Budget vs actual for a list of projects (rows prepared by the projects page). */
class ProjectsExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param list<array<int, string|float|null>> $rows */
    public function __construct(protected array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Reference', 'Project', 'Stage', 'County', 'Project manager', 'Start', 'Expected completion',
            'Original budget', 'Current budget', 'Actual cost', 'Committed', 'Forecast final cost', 'Variance', 'Utilisation %', 'Progress %', 'Paid', 'Unpaid approved'];
    }

    public function title(): string
    {
        return 'Projects';
    }
}
