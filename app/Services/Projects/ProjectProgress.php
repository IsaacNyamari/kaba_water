<?php

namespace App\Services\Projects;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Progress from milestone weights (not a typed-in percentage).
 *
 *  Actual %  = weight of milestones done or waived ÷ total weight
 *  Planned % = weight of milestones planned on or before today ÷ total weight
 */
class ProjectProgress
{
    /**
     * @return array{actual: float, planned: float, behind: bool, delayed: Collection, overdue_tasks: int,
     *     blocked_tasks: int, next_milestone: mixed, days_late: int}
     */
    public function summary(Project $project): array
    {
        $project->loadMissing(['milestones', 'tasks']);
        $milestones = $project->milestones;
        $total = max(1, (int) $milestones->sum('weight'));

        $done = (int) $milestones->filter->isSettled()->sum('weight');
        $planned = (int) $milestones->filter(fn ($m) => $m->planned_date && $m->planned_date->lte(today()))->sum('weight');

        $actual = $milestones->isEmpty() ? 0.0 : round($done / $total * 100, 1);
        $plannedPct = $milestones->isEmpty() ? 0.0 : round($planned / $total * 100, 1);
        $delayed = $milestones->filter->isOverdue()->values();

        $daysLate = 0;
        if ($project->expected_completion_date && ! $project->isClosed() && $project->expected_completion_date->lt(today())) {
            $daysLate = (int) $project->expected_completion_date->diffInDays(today());
        } elseif ($delayed->isNotEmpty()) {
            $daysLate = (int) $delayed->first()->planned_date->diffInDays(today());
        }

        return [
            'actual' => $actual,
            'planned' => $plannedPct,
            'behind' => $actual < $plannedPct || $delayed->isNotEmpty(),
            'delayed' => $delayed,
            'overdue_tasks' => $project->tasks->filter->isOverdue()->count(),
            'blocked_tasks' => $project->tasks->where('status', 'blocked')->count(),
            'next_milestone' => $milestones->first(fn ($m) => ! $m->isSettled()),
            'days_late' => $daysLate,
        ];
    }
}
