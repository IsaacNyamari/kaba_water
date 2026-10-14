<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Projects\ProjectAlerts;
use App\Support\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Daily project reminders: overdue tasks, milestones due within 3 days, and
 * active drilling-stage projects with no progress report yesterday. Each alert
 * is sent at most once per day (cache key), so re-running never duplicates.
 */
class SendProjectAlerts extends Command
{
    protected $signature = 'projects:alerts';

    protected $description = 'Notify project teams about overdue tasks, upcoming milestones and missed daily reports';

    /** Stages where crews are on site and a daily report is expected. */
    private const SITE_STAGES = ['site_preparation', 'drilling', 'casing_installation', 'pump_installation', 'testing', 'commissioning'];

    public function handle(): int
    {
        $sent = 0;

        Project::withoutGlobalScope('vendor')->active()->with(['manager', 'tasks.assignee', 'milestones'])
            ->chunkById(100, function ($projects) use (&$sent) {
                foreach ($projects as $project) {
                    foreach ($project->tasks->filter->isOverdue() as $task) {
                        $sent += $this->once("task-overdue:{$task->id}", fn () => ProjectAlerts::notifyUser(
                            $task->assignee ?? $project->manager, 'Task overdue',
                            "{$project->reference} · {$task->title} was due {$task->due_date->format('d M')}", $project, 'tasks', 'clock'));
                    }

                    foreach ($project->milestones->reject->isSettled()->filter(fn ($m) => $m->planned_date?->between(today(), today()->addDays(3))) as $m) {
                        $sent += $this->once("milestone-soon:{$m->id}", fn () => ProjectAlerts::notifyUser(
                            $project->manager, 'Milestone coming up', "{$project->reference} · {$m->name} on {$m->planned_date->format('d M')}", $project, 'milestones', 'flag'));
                    }

                    if (in_array($project->status, self::SITE_STAGES, true)
                        && ! $project->progressReports()->whereDate('report_date', today()->subDay())->exists()) {
                        $sent += $this->once("report-missed:{$project->id}", fn () => Notifier::send(
                            ProjectAlerts::stakeholders($project, 'projects.report'),
                            'Daily report missing', "{$project->reference} has no progress report for ".today()->subDay()->format('d M').'.',
                            route('projects.show', ['project' => $project, 'tab' => 'progress']), 'clipboard-document-list', 'project'));
                    }
                }
            });

        $this->info("Sent {$sent} project alert(s).");

        return self::SUCCESS;
    }

    private function once(string $key, \Closure $send): int
    {
        if (! Cache::add('project-alert:'.$key.':'.today()->toDateString(), true, now()->addDays(2))) {
            return 0;
        }

        $send();

        return 1;
    }
}
