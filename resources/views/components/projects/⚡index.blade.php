<?php

use App\Exports\Projects\ProjectsExport;
use App\Livewire\Concerns\RefreshesOnActivity;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectFinancials;
use App\Services\Projects\ProjectProgress;
use App\Support\Money\MoneyMath;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/*
 * Project Management dashboard + project list. Every figure is computed
 * server-side from the ledgers (ProjectFinancials / ProjectProgress).
 */
new #[Layout('layouts.vendor'), Title('Projects')] class extends Component {
    use RefreshesOnActivity, WithPagination;

    #[Url(as: 'q', history: true)] public string $search = '';
    #[Url(history: true)] public string $status = '';
    #[Url(history: true)] public string $county = '';
    #[Url(history: true)] public string $manager = '';
    #[Url(history: true)] public string $from = '';
    #[Url(history: true)] public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'county', 'manager', 'from', 'to');
        $this->resetPage();
    }

    protected function filtered()
    {
        return Project::query()
            ->with(['manager', 'milestones', 'tasks', 'expenses', 'assignments', 'materials.movements'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('reference', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $this->status === 'delayed'
                ? $q->active()->whereDate('expected_completion_date', '<', today())
                : $q->where('status', $this->status))
            ->when($this->county, fn ($q) => $q->where('county', $this->county))
            ->when($this->manager, fn ($q) => $q->where('project_manager_id', $this->manager))
            ->when($this->from, fn ($q) => $q->whereDate('start_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('start_date', '<=', $this->to));
    }

    /** Portfolio figures across every non-cancelled project in the workspace. */
    #[Computed]
    public function portfolio(): array
    {
        $financials = app(ProjectFinancials::class);
        $progress = app(ProjectProgress::class);

        $projects = Project::query()->with(['milestones', 'tasks', 'expenses', 'assignments', 'materials.movements'])
            ->where('status', '!=', 'cancelled')->get();

        $totals = ['budget' => '0', 'actual' => '0', 'forecast' => '0', 'unpaid' => '0', 'variance' => '0'];
        $overBudget = 0;
        $delayed = 0;
        $progressSum = 0;

        foreach ($projects as $project) {
            $f = $financials->summary($project);
            $p = $progress->summary($project);
            $totals['budget'] = MoneyMath::add($totals['budget'], $f['current_budget']);
            $totals['actual'] = MoneyMath::add($totals['actual'], $f['actual_cost']);
            $totals['forecast'] = MoneyMath::add($totals['forecast'], $f['forecast_final']);
            $totals['unpaid'] = MoneyMath::add($totals['unpaid'], $f['unpaid_approved']);
            $totals['variance'] = MoneyMath::add($totals['variance'], $f['variance']);
            $overBudget += MoneyMath::isNegative($f['variance']) ? 1 : 0;
            $delayed += $project->isActive() && $p['behind'] ? 1 : 0;
            $progressSum += $p['actual'];
        }

        return $totals + [
            'active' => $projects->filter->isActive()->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'on_hold' => $projects->where('status', 'on_hold')->count(),
            'delayed' => $delayed,
            'over_budget' => $overBudget,
            'avg_progress' => $projects->count() ? round($progressSum / $projects->count(), 1) : 0,
            'due_soon' => $projects->filter(fn ($p) => $p->isActive() && $p->expected_completion_date
                && $p->expected_completion_date->between(today(), today()->addDays(14)))->values(),
        ];
    }

    public function export()
    {
        $this->authorize('viewAny', Project::class);
        $financials = app(ProjectFinancials::class);
        $progress = app(ProjectProgress::class);

        $rows = $this->filtered()->orderBy('reference')->get()->map(function (Project $p) use ($financials, $progress) {
            $f = $financials->summary($p);

            return [$p->reference, $p->name, $p->stageLabel(), $p->county, $p->manager?->name,
                $p->start_date?->toDateString(), $p->expected_completion_date?->toDateString(),
                $f['original_budget'], $f['current_budget'], $f['actual_cost'], $f['committed'], $f['forecast_final'],
                $f['variance'], $f['utilization'], $progress->summary($p)['actual'], $f['paid'], $f['unpaid_approved']];
        })->all();

        return Excel::download(new ProjectsExport($rows), 'projects-'.now()->format('Y-m-d').'.xlsx');
    }

    public function with(): array
    {
        $financials = app(ProjectFinancials::class);
        $progress = app(ProjectProgress::class);
        $projects = $this->filtered()->latest('id')->paginate(12);

        return [
            'projects' => $projects,
            'rowStats' => $projects->getCollection()->mapWithKeys(fn ($p) => [$p->id => [
                'f' => $financials->summary($p),
                'p' => $progress->summary($p),
            ]]),
            'counties' => Project::query()->whereNotNull('county')->distinct()->orderBy('county')->pluck('county'),
            'managers' => User::query()->whereIn('id', Project::query()->whereNotNull('project_manager_id')->select('project_manager_id'))->orderBy('name')->get(['id', 'name']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-amber flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                <flux:icon.briefcase variant="mini" class="size-5" />
            </span>
            <div>
                <flux:heading size="lg">Borehole projects</flux:heading>
                <flux:text class="text-sm">Progress, materials and costs from Day One to handover — tracked against your approved budgets.</flux:text>
            </div>
        </div>
        <div class="flex gap-2">
            <flux:button icon="arrow-down-tray" wire:click="export">Export</flux:button>
            @can('create', App\Models\Project::class)
                <flux:button variant="primary" icon="plus" :href="route('projects.create')" wire:navigate>New project</flux:button>
            @endcan
        </div>
    </div>

    {{-- Portfolio KPIs --}}
    @php $k = $this->portfolio; @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Active projects', $k['active'], 'play-circle', 'brand', $k['completed'].' completed · '.$k['on_hold'].' on hold'],
            ['Delayed', $k['delayed'], 'clock', $k['delayed'] ? 'rose' : 'emerald', $k['due_soon']->count().' due in the next 14 days'],
            ['Over budget', $k['over_budget'], 'exclamation-triangle', $k['over_budget'] ? 'rose' : 'emerald', 'Forecast above the approved budget'],
            ['Average completion', $k['avg_progress'].'%', 'chart-bar', 'sky', 'Weighted by milestones'],
        ] as [$label, $value, $icon, $tone, $hint])
            <div wire:key="kpi-{{ $loop->index }}" class="stat-card tone-{{ $tone }} p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
                    <flux:icon :name="$icon" variant="mini" class="size-5 text-zinc-400" />
                </div>
                <div class="mt-2 font-mono text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $value }}</div>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['Approved budgets', $k['budget']],
            ['Actual expenditure', $k['actual']],
            ['Forecast final cost', $k['forecast']],
            ['Variance vs budget', $k['variance']],
            ['Unpaid supplier bills', $k['unpaid']],
        ] as [$label, $amount])
            <div wire:key="money-{{ $loop->index }}" class="app-card p-4">
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
                <div class="mt-1 font-mono text-lg font-semibold {{ MoneyMath::isNegative($amount) ? 'text-rose-600' : 'text-zinc-900 dark:text-zinc-100' }}">{{ MoneyMath::format($amount) }}</div>
            </div>
        @endforeach
    </div>

    @if ($k['due_soon']->isNotEmpty())
        <flux:callout icon="calendar-days" color="amber">
            <flux:callout.heading>Approaching deadlines</flux:callout.heading>
            <flux:callout.text>
                @foreach ($k['due_soon'] as $p)
                    <a href="{{ route('projects.show', $p) }}" wire:navigate class="font-medium underline-offset-2 hover:underline">{{ $p->reference }}</a> due {{ $p->expected_completion_date->format('d M') }}@if (! $loop->last) · @endif
                @endforeach
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Filters --}}
    <div class="app-card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search name or reference" class="sm:col-span-2" />
        <flux:select wire:model.live="status">
            <flux:select.option value="">All stages</flux:select.option>
            <flux:select.option value="delayed">Delayed (past due)</flux:select.option>
            @foreach (config('projects.stages') as $key => $label)
                <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="county">
            <flux:select.option value="">All counties</flux:select.option>
            @foreach ($counties as $c)
                <flux:select.option value="{{ $c }}">{{ $c }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="manager">
            <flux:select.option value="">All managers</flux:select.option>
            @foreach ($managers as $m)
                <flux:select.option value="{{ $m->id }}">{{ $m->name }}</flux:select.option>
            @endforeach
        </flux:select>
        {{-- Start-date range: two equal columns that shrink instead of overflowing. --}}
        <div class="grid min-w-0 grid-cols-2 gap-2 sm:col-span-2">
            <flux:input type="date" wire:model.live="from" aria-label="Started from" class="min-w-0" />
            <flux:input type="date" wire:model.live="to" aria-label="Started to" class="min-w-0" />
        </div>
    </div>

    {{-- Project cards (mobile friendly) --}}
    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse ($projects as $project)
            @php $f = $rowStats[$project->id]['f']; $p = $rowStats[$project->id]['p']; @endphp
            <a wire:key="project-{{ $project->id }}" href="{{ route('projects.show', $project) }}" wire:navigate
                class="app-card group block space-y-4 p-5 transition hover:ring-2 hover:ring-brand-400/50">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-zinc-500">{{ $project->reference }}</span>
                            <flux:badge size="sm" :color="$project->stageColor()">{{ $project->stageLabel() }}</flux:badge>
                            @if ($project->isActive() && $p['behind'])
                                <flux:badge size="sm" color="rose" icon="clock">Behind</flux:badge>
                            @endif
                            @if (MoneyMath::isNegative($f['variance']))
                                <flux:badge size="sm" color="rose" icon="exclamation-triangle">Over budget</flux:badge>
                            @endif
                        </div>
                        <h3 class="mt-1 truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $project->name }}</h3>
                        <p class="text-xs text-zinc-500">{{ collect([$project->sub_county, $project->county])->filter()->implode(', ') ?: 'No location yet' }} · PM {{ $project->manager?->name ?? '—' }}</p>
                    </div>
                    <span class="font-mono text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $p['actual'] }}%</span>
                </div>

                <div>
                    <div class="relative h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-brand-500" style="width: {{ min(100, $p['actual']) }}%"></div>
                        <div class="absolute inset-y-0 w-0.5 bg-zinc-900/60 dark:bg-white/70" style="left: {{ min(100, $p['planned']) }}%" title="Planned {{ $p['planned'] }}%"></div>
                    </div>
                    <div class="mt-1 flex justify-between text-[11px] text-zinc-500"><span>Actual {{ $p['actual'] }}%</span><span>Planned {{ $p['planned'] }}%</span></div>
                </div>

                <dl class="grid grid-cols-3 gap-2 text-xs">
                    <div><dt class="text-zinc-500">Budget</dt><dd class="font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ MoneyMath::format($f['current_budget']) }}</dd></div>
                    <div><dt class="text-zinc-500">Actual</dt><dd class="font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ MoneyMath::format($f['actual_cost']) }}</dd></div>
                    <div><dt class="text-zinc-500">Forecast</dt><dd class="font-mono font-medium {{ MoneyMath::isNegative($f['variance']) ? 'text-rose-600' : 'text-zinc-900 dark:text-zinc-100' }}">{{ MoneyMath::format($f['forecast_final']) }}</dd></div>
                </dl>
            </a>
        @empty
            <div class="app-card p-10 text-center lg:col-span-2 2xl:col-span-3">
                <flux:icon.briefcase class="mx-auto size-10 text-brand-500" />
                <p class="mt-3 font-medium text-zinc-900 dark:text-zinc-100">No projects match</p>
                <p class="text-sm text-zinc-500">Create a project to start tracking a borehole from Day One.</p>
                @if ($search || $status || $county || $manager || $from || $to)
                    <flux:button size="sm" class="mt-4" wire:click="resetFilters">Clear filters</flux:button>
                @endif
            </div>
        @endforelse
    </div>

    {{ $projects->links() }}
</div>
