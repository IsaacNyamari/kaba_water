<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Projects\ProjectAlerts;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public ?int $editingId = null;
    public string $title = '';
    public string $description = '';
    public string $milestone_id = '';
    public string $parent_id = '';
    public string $depends_on_id = '';
    public string $assigned_to = '';
    public string $priority = 'normal';
    public string $status = 'todo';
    public string $progress = '0';
    public string $start_date = '';
    public string $due_date = '';
    public string $estimated_cost = '0';
    public string $blocker = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function startAdd(): void
    {
        $this->authorize('update', $this->project);
        $this->reset('editingId', 'title', 'description', 'milestone_id', 'parent_id', 'depends_on_id', 'assigned_to', 'blocker');
        $this->priority = 'normal';
        $this->status = 'todo';
        $this->progress = '0';
        $this->estimated_cost = '0';
        $this->start_date = today()->toDateString();
        $this->due_date = today()->addDays(7)->toDateString();
        $this->resetValidation();
        Flux::modal('task')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('update', $this->project);
        $t = $this->project->tasks()->findOrFail($id);
        $this->editingId = $t->id;
        foreach (['title', 'description', 'priority', 'status', 'blocker'] as $f) {
            $this->{$f} = (string) ($t->{$f} ?? '');
        }
        foreach (['milestone_id', 'parent_id', 'depends_on_id', 'assigned_to', 'progress'] as $f) {
            $this->{$f} = (string) ($t->{$f} ?? '');
        }
        $this->estimated_cost = (string) $t->estimated_cost;
        $this->start_date = $t->start_date?->toDateString() ?? '';
        $this->due_date = $t->due_date?->toDateString() ?? '';
        $this->resetValidation();
        Flux::modal('task')->show();
    }

    public function save(): void
    {
        $this->authorize('update', $this->project);
        $projectTasks = Rule::exists('project_tasks', 'id')->where('project_id', $this->project->id);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'milestone_id' => ['nullable', Rule::exists('project_milestones', 'id')->where('project_id', $this->project->id)],
            'parent_id' => ['nullable', $projectTasks, Rule::notIn([$this->editingId])],
            'depends_on_id' => ['nullable', $projectTasks, Rule::notIn([$this->editingId])],
            'assigned_to' => ['nullable', 'exists:users,id,vendor_id,'.$this->project->vendor_id],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['required', Rule::in(array_keys(ProjectTask::STATUSES))],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'blocker' => ['nullable', 'string', 'max:255'],
        ]);

        $data = collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['estimated_cost'] = MoneyMath::str($this->estimated_cost);
        $data['progress'] = $data['status'] === 'done' ? 100 : (int) $data['progress'];
        $data['completed_at'] = $data['status'] === 'done' ? now() : null;

        if ($this->editingId) {
            $task = $this->project->tasks()->findOrFail($this->editingId);
            $previousAssignee = $task->assigned_to;
            if ($task->status === 'done' && $data['status'] === 'done') {
                $data['completed_at'] = $task->completed_at;
            }
            $task->update($data);
            $this->project->log('task', "Task updated: {$task->title} ({$task->status})", ['task_id' => $task->id]);
        } else {
            $previousAssignee = null;
            $task = $this->project->tasks()->create($data);
            $this->project->log('task', "Task added: {$task->title}", ['task_id' => $task->id]);
        }

        if ($task->assigned_to && $task->assigned_to !== $previousAssignee) {
            ProjectAlerts::notifyUser($task->assignee, 'New project task', "{$this->project->reference} · {$task->title}".($task->due_date ? ' · due '.$task->due_date->format('d M') : ''), $this->project, 'tasks', 'clipboard-document-check');
        }

        Flux::modal('task')->close();
        Flux::toast(variant: 'success', text: 'Task saved.');
        $this->dispatch('project-changed');
    }

    public function setStatus(int $id, string $status): void
    {
        $this->authorize('update', $this->project);
        abort_unless(array_key_exists($status, ProjectTask::STATUSES), 422);
        $task = $this->project->tasks()->findOrFail($id);
        $task->update(['status' => $status, 'progress' => $status === 'done' ? 100 : $task->progress, 'completed_at' => $status === 'done' ? now() : null]);
        $this->project->log('task', "Task {$task->title} → ".ProjectTask::STATUSES[$status], ['task_id' => $task->id]);
        Flux::toast(text: $task->title.' · '.ProjectTask::STATUSES[$status]);
        $this->dispatch('project-changed');
    }

    public function delete(int $id): void
    {
        $this->authorize('update', $this->project);
        $task = $this->project->tasks()->findOrFail($id);
        $this->project->log('task', "Task removed: {$task->title}", ['task_id' => $task->id]);
        $task->delete();
        Flux::toast(text: 'Task removed.');
        $this->dispatch('project-changed');
    }

    public function with(): array
    {
        $tasks = $this->project->tasks()->with(['assignee', 'milestone', 'dependsOn', 'parent'])
            ->orderByRaw("CASE status WHEN 'blocked' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'todo' THEN 2 ELSE 3 END")
            ->orderBy('due_date')->get();

        // Gantt window: project start → the later of expected completion and the last due date.
        $start = $this->project->start_date ?? today();
        $end = collect([$this->project->expected_completion_date, $tasks->max('due_date'), $start->addDays(14)])->filter()->max();

        return [
            'tasks' => $tasks,
            'team' => User::query()->where('vendor_id', $this->project->vendor_id)->whereHas('roles')->orderBy('name')->get(['id', 'name']),
            'ganttStart' => $start,
            'ganttDays' => max(1, $start->diffInDays($end)),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">Tasks & schedule</flux:heading>
            <flux:text class="text-sm">Open task estimates feed the estimate-to-complete. Overdue and blocked work is flagged.</flux:text>
        </div>
        @can('update', $project)
            <flux:button variant="primary" icon="plus" wire:click="startAdd">Add task</flux:button>
        @endcan
    </div>

    {{-- Gantt-style timeline --}}
    @if ($tasks->whereNotNull('due_date')->isNotEmpty())
        <div class="app-card space-y-2 overflow-x-auto p-4">
            <div class="flex min-w-[640px] justify-between text-[11px] text-zinc-500">
                <span>{{ $ganttStart->format('d M') }}</span>
                <span>Today</span>
                <span>{{ $ganttStart->addDays($ganttDays)->format('d M Y') }}</span>
            </div>
            @php $todayPct = min(100, max(0, $ganttStart->diffInDays(today(), false) / $ganttDays * 100)); @endphp
            @foreach ($tasks->whereNotNull('due_date') as $t)
                @php
                    $s = $t->start_date ?? $t->due_date;
                    $left = max(0, $ganttStart->diffInDays($s, false) / $ganttDays * 100);
                    $width = max(1.5, ($s->diffInDays($t->due_date) + 1) / $ganttDays * 100);
                @endphp
                <div wire:key="gantt-{{ $t->id }}" class="relative flex min-w-[640px] items-center gap-3">
                    <span class="w-40 shrink-0 truncate text-xs text-zinc-600 dark:text-zinc-300">{{ $t->title }}</span>
                    <div class="relative h-5 flex-1 rounded bg-zinc-100 dark:bg-white/5">
                        <div class="absolute inset-y-0 w-px bg-rose-400" style="left: {{ $todayPct }}%"></div>
                        <div class="absolute inset-y-0.5 rounded {{ $t->status === 'done' ? 'bg-emerald-500' : ($t->isOverdue() ? 'bg-rose-500' : ($t->status === 'blocked' ? 'bg-amber-500' : 'bg-brand-500')) }}"
                            style="left: {{ min(98, $left) }}%; width: {{ min(100 - min(98, $left), $width) }}%" title="{{ $s->format('d M') }} → {{ $t->due_date->format('d M') }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($tasks as $t)
            <div wire:key="task-{{ $t->id }}" class="app-card space-y-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" :color="['todo' => 'zinc', 'in_progress' => 'blue', 'blocked' => 'amber', 'done' => 'emerald'][$t->status]">{{ ProjectTask::STATUSES[$t->status] }}</flux:badge>
                            @if (in_array($t->priority, ['high', 'urgent'])) <flux:badge size="sm" color="rose">{{ ucfirst($t->priority) }}</flux:badge> @endif
                            @if ($t->isOverdue()) <flux:badge size="sm" color="rose" icon="clock">Overdue</flux:badge> @endif
                            @if ($t->isWaiting()) <flux:badge size="sm" color="amber">Waiting on {{ $t->dependsOn->title }}</flux:badge> @endif
                        </div>
                        <h4 class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $t->parent ? $t->parent->title.' › ' : '' }}{{ $t->title }}</h4>
                        <p class="text-xs text-zinc-500">
                            {{ $t->assignee?->name ?? 'Unassigned' }}
                            · {{ $t->start_date?->format('d M') ?? '—' }} → {{ $t->due_date?->format('d M') ?? '—' }}
                            @if ($t->milestone) · {{ $t->milestone->name }} @endif
                            · est. {{ MoneyMath::format((string) $t->estimated_cost) }}
                        </p>
                    </div>
                    <span class="font-mono text-sm">{{ $t->progress }}%</span>
                </div>
                @if ($t->blocker) <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Blocker: {{ $t->blocker }}</p> @endif
                @can('update', $project)
                    <div class="flex flex-wrap justify-end gap-1">
                        @foreach (ProjectTask::STATUSES as $key => $label)
                            @if ($key !== $t->status)
                                <flux:button size="xs" variant="ghost" wire:click="setStatus({{ $t->id }}, '{{ $key }}')">{{ $label }}</flux:button>
                            @endif
                        @endforeach
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $t->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash" class="!text-rose-600" wire:click="delete({{ $t->id }})" wire:confirm="Remove this task?" />
                    </div>
                @endcan
            </div>
        @empty
            <div class="app-card p-10 text-center text-sm text-zinc-500 lg:col-span-2">No tasks yet.</div>
        @endforelse
    </div>

    <flux:modal name="task" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Edit task' : 'Add task' }}</flux:heading>
            <flux:input wire:model="title" label="Title" />
            <flux:textarea wire:model="description" label="Description" rows="2" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="assigned_to" label="Assignee">
                    <flux:select.option value="">— Unassigned —</flux:select.option>
                    @foreach ($team as $u) <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option> @endforeach
                </flux:select>
                <flux:select wire:model="priority" label="Priority">
                    @foreach (['low', 'normal', 'high', 'urgent'] as $p) <flux:select.option value="{{ $p }}">{{ ucfirst($p) }}</flux:select.option> @endforeach
                </flux:select>
                <flux:select wire:model="status" label="Status">
                    @foreach (ProjectTask::STATUSES as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
                </flux:select>
                <flux:input type="date" wire:model="start_date" label="Start" />
                <flux:input type="date" wire:model="due_date" label="Due" />
                <flux:input type="number" wire:model="progress" label="Progress %" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="milestone_id" label="Milestone">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($project->milestones as $m) <flux:select.option value="{{ $m->id }}">{{ $m->name }}</flux:select.option> @endforeach
                </flux:select>
                <flux:select wire:model="parent_id" label="Subtask of">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($tasks->where('id', '!=', $editingId) as $o) <flux:select.option value="{{ $o->id }}">{{ $o->title }}</flux:select.option> @endforeach
                </flux:select>
                <flux:select wire:model="depends_on_id" label="Depends on">
                    <flux:select.option value="">— None —</flux:select.option>
                    @foreach ($tasks->where('id', '!=', $editingId) as $o) <flux:select.option value="{{ $o->id }}">{{ $o->title }}</flux:select.option> @endforeach
                </flux:select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="number" step="0.01" wire:model="estimated_cost" label="Estimated cost" />
                <flux:input wire:model="blocker" label="Blocker (if any)" />
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save task</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
