<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Support\Collection;

/**
 * Project notifications (in-app + live over Reverb via Notifier).
 * Budget alerts fire once per threshold — the level reached is stored on the
 * project so the same warning is never sent twice.
 */
class ProjectAlerts
{
    public function __construct(protected ProjectFinancials $financials) {}

    /** The project manager plus everyone in the vendor team holding the permission. */
    public static function stakeholders(Project $project, string $permission): Collection
    {
        return Notifier::vendorTeam($project->vendor_id, $permission)
            ->when($project->manager, fn ($c) => $c->push($project->manager))
            ->unique('id');
    }

    public function checkBudget(Project $project): void
    {
        $project->refresh();
        $utilization = $this->financials->summary($project)['utilization'];

        $reached = collect(config('projects.budget_alerts'))
            ->filter(fn ($threshold) => $utilization >= $threshold)
            ->max() ?? 0;

        if ($reached <= $project->budget_alert_level) {
            return;
        }

        $project->forceFill(['budget_alert_level' => $reached])->saveQuietly();
        $project->log('budget_alert', "Budget utilisation reached {$reached}% ({$utilization}% used)", ['threshold' => $reached]);

        Notifier::send(
            self::stakeholders($project, 'projects.budget.revise'),
            $reached >= 100 ? "{$project->reference} is over budget" : "{$project->reference} has used {$reached}% of its budget",
            "{$project->name} — {$utilization}% of the approved budget spent.",
            route('projects.show', ['project' => $project, 'tab' => 'financials']),
            'exclamation-triangle',
            'project_budget',
        );
    }

    public static function notifyUser(?User $user, string $title, string $body, Project $project, string $tab, string $icon = 'briefcase'): void
    {
        Notifier::send($user, $title, $body, route('projects.show', ['project' => $project, 'tab' => $tab]), $icon, 'project');
    }
}
