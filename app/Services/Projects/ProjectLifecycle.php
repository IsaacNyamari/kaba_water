<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Support\Money\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Stage transitions (config/projects.php) and budget revisions, both fully logged. */
class ProjectLifecycle
{
    /** @return list<string> */
    public function allowedFrom(Project $project): array
    {
        return config("projects.transitions.{$project->status}", []);
    }

    public function transition(Project $project, string $to, ?string $note = null): void
    {
        if (! in_array($to, $this->allowedFrom($project), true)) {
            throw ValidationException::withMessages([
                'status' => "A project in {$project->stageLabel()} cannot move to ".config("projects.stages.{$to}", $to).'.',
            ]);
        }

        DB::transaction(function () use ($project, $to, $note) {
            $from = $project->status;
            $project->update(['status' => $to]);
            $project->log('status_changed', 'Stage changed from '.config("projects.stages.{$from}").' to '.config("projects.stages.{$to}").($note ? " — {$note}" : ''), [
                'from' => $from, 'to' => $to, 'note' => $note,
            ]);
        });
    }

    /** A revised budget. The original stays untouched for audit. */
    public function reviseBudget(Project $project, string $newBudget, string $reason): void
    {
        DB::transaction(function () use ($project, $newBudget, $reason) {
            $old = MoneyMath::str((string) $project->current_budget);
            $new = MoneyMath::str($newBudget);

            $project->update(['current_budget' => $new, 'budget_alert_level' => 0]);
            $project->log('budget_revised', "Budget revised from {$old} to {$new} — {$reason}", [
                'from' => $old, 'to' => $new, 'reason' => $reason, 'original' => (string) $project->original_budget,
            ]);
        });

        app(ProjectAlerts::class)->checkBudget($project);
    }
}
