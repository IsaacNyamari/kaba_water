<?php

use App\Exports\Projects\ProjectLedgerExport;
use App\Livewire\Concerns\RefreshesOnActivity;
use App\Models\Project;
use App\Services\Projects\ProjectFinancials;
use App\Services\Projects\ProjectLifecycle;
use App\Services\Projects\ProjectProgress;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/*
 * One project: header with stage control, tabbed workspace. Overview,
 * Financials and Activity live here; the other tabs are child components
 * (resources/views/components/projects/tabs).
 */
new #[Layout('layouts.vendor')] class extends Component {
    use RefreshesOnActivity;

    public Project $project;

    #[Url(history: true)]
    public string $tab = 'overview';

    public string $toStage = '';
    public string $stageNote = '';

    public string $newBudget = '';
    public string $budgetReason = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function title(): string
    {
        return $this->project->reference.' · '.$this->project->name;
    }

    /** Child tabs dispatch this after changing data so the header figures stay current. */
    #[On('project-changed')]
    public function refreshProject(): void
    {
        $this->project->refresh();
        unset($this->financials, $this->progress);
    }

    #[Computed]
    public function financials(): array
    {
        return app(ProjectFinancials::class)->summary($this->project->fresh());
    }

    #[Computed]
    public function progress(): array
    {
        return app(ProjectProgress::class)->summary($this->project->fresh());
    }

    public function startStageChange(string $to): void
    {
        $this->authorize('update', $this->project);
        $this->toStage = $to;
        $this->stageNote = '';
        Flux::modal('stage-change')->show();
    }

    public function changeStage(): void
    {
        $this->authorize('update', $this->project);
        $this->validate(['stageNote' => ['nullable', 'string', 'max:500']]);

        app(ProjectLifecycle::class)->transition($this->project, $this->toStage, $this->stageNote ?: null);

        Flux::modal('stage-change')->close();
        Flux::toast(variant: 'success', heading: 'Stage updated', text: $this->project->stageLabel());
        $this->refreshProject();
    }

    public function startBudgetRevision(): void
    {
        $this->authorize('reviseBudget', $this->project);
        $this->newBudget = (string) $this->project->current_budget;
        $this->budgetReason = '';
        $this->resetValidation();
        Flux::modal('revise-budget')->show();
    }

    public function reviseBudget(): void
    {
        $this->authorize('reviseBudget', $this->project);
        $this->validate([
            'newBudget' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'budgetReason' => ['required', 'string', 'max:500'],
        ], [], ['newBudget' => 'new budget', 'budgetReason' => 'reason']);

        app(ProjectLifecycle::class)->reviseBudget($this->project, $this->newBudget, $this->budgetReason);

        Flux::modal('revise-budget')->close();
        Flux::toast(variant: 'success', heading: 'Budget revised', text: 'The original budget is kept for audit.');
        $this->refreshProject();
    }

    public function exportLedger()
    {
        $this->authorize('view', $this->project);

        return Excel::download(new ProjectLedgerExport($this->project), "{$this->project->reference}-cost-statement.xlsx");
    }

    public function with(): array
    {
        return [
            'nextStages' => app(ProjectLifecycle::class)->allowedFrom($this->project),
            'tabs' => [
                'overview' => ['Overview', 'home'],
                'milestones' => ['Milestones', 'flag'],
                'progress' => ['Progress', 'clipboard-document-list'],
                'tasks' => ['Tasks & schedule', 'calendar-days'],
                'expenses' => ['Expenses', 'receipt-percent'],
                'materials' => ['Materials', 'cube'],
                'resources' => ['Labour & equipment', 'user-group'],
                'financials' => ['Financials', 'banknotes'],
                'technical' => ['Technical & documents', 'beaker'],
                'closure' => ['Closure', 'check-badge'],
                'activity' => ['Activity', 'clock'],
            ],
            'activities' => $this->tab === 'activity' || $this->tab === 'overview'
                ? $this->project->activities()->with('user')->limit($this->tab === 'activity' ? 200 : 8)->get()
                : collect(),
        ];
    }
};
?>

@php
    $f = $this->financials;
    $p = $this->progress;
@endphp

<div class="p-4 lg:p-6 space-y-6">
    {{-- Header --}}
    <div class="stat-card tone-amber space-y-4 p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('projects.index') }}" wire:navigate class="text-xs text-zinc-500 hover:underline">Projects</a>
                    <span class="text-zinc-400">/</span>
                    <span class="font-mono text-xs text-zinc-500">{{ $project->reference }}</span>
                    <flux:badge size="sm" :color="$project->stageColor()">{{ $project->stageLabel() }}</flux:badge>
                    @if ($project->isActive() && $p['behind'])
                        <flux:badge size="sm" color="rose" icon="clock">Behind schedule{{ $p['days_late'] ? ' · '.$p['days_late'].' days' : '' }}</flux:badge>
                    @endif
                    @if (MoneyMath::isNegative($f['variance']))
                        <flux:badge size="sm" color="rose" icon="exclamation-triangle">Forecast over budget</flux:badge>
                    @endif
                </div>
                <flux:heading size="xl" class="mt-1">{{ $project->name }}</flux:heading>
                <flux:text class="text-sm">
                    {{ collect([$project->sub_county, $project->county])->filter()->implode(', ') ?: 'No location yet' }}
                    · PM {{ $project->manager?->name ?? 'not assigned' }}
                    · {{ $project->start_date?->format('d M Y') ?? '—' }} → {{ $project->expected_completion_date?->format('d M Y') ?? '—' }}
                </flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('update', $project)
                    @if ($nextStages)
                        <flux:dropdown>
                            <flux:button icon="arrow-path" icon-trailing="chevron-down">Move stage</flux:button>
                            <flux:menu>
                                @foreach ($nextStages as $stage)
                                    <flux:menu.item wire:click="startStageChange('{{ $stage }}')">{{ config("projects.stages.{$stage}") }}</flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                    <flux:button icon="pencil-square" :href="route('projects.edit', $project)" wire:navigate>Edit</flux:button>
                @endcan
                <flux:button icon="arrow-down-tray" wire:click="exportLedger">Export</flux:button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl bg-white/70 p-3 dark:bg-white/5">
                <div class="text-xs text-zinc-500">Progress (planned {{ $p['planned'] }}%)</div>
                <div class="font-mono text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $p['actual'] }}%</div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10"><div class="h-full rounded-full bg-brand-500" style="width: {{ min(100, $p['actual']) }}%"></div></div>
            </div>
            @foreach ([
                ['Current budget', $f['current_budget']],
                ['Actual cost', $f['actual_cost']],
                ['Forecast final', $f['forecast_final']],
                ['Variance', $f['variance']],
            ] as [$label, $amount])
                <div class="rounded-xl bg-white/70 p-3 dark:bg-white/5">
                    <div class="text-xs text-zinc-500">{{ $label }}</div>
                    <div class="font-mono text-xl font-semibold {{ MoneyMath::isNegative($amount) ? 'text-rose-600' : 'text-zinc-900 dark:text-zinc-100' }}">{{ MoneyMath::format($amount) }}</div>
                    @if ($label === 'Actual cost')
                        <div class="text-[11px] text-zinc-500">{{ $f['utilization'] }}% of budget used</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Tabs --}}
    <div class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
        <nav class="flex min-w-max gap-1 rounded-xl bg-brand-900/5 p-1 ring-1 ring-brand-900/10 dark:bg-white/10 dark:ring-white/15">
            @foreach ($tabs as $key => [$label, $icon])
                <button type="button" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition {{ $tab === $key ? 'bg-brand-500 text-white shadow-sm dark:bg-brand-400 dark:text-brand-950' : 'text-zinc-600 hover:bg-brand-900/5 dark:text-zinc-300 dark:hover:bg-white/10' }}">
                    <flux:icon :name="$icon" variant="micro" />
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ═══ Overview ═══ --}}
    @if ($tab === 'overview')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <flux:card class="space-y-4">
                    <flux:heading size="sm">Project details</flux:heading>
                    @if ($project->description)
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $project->description }}</p>
                    @endif
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        @foreach ([
                            'Planned depth' => $project->planned_depth_m ? $project->planned_depth_m.' m' : null,
                            'Actual depth' => $project->actual_depth_m ? $project->actual_depth_m.' m' : null,
                            'Diameter' => $project->diameter_mm ? $project->diameter_mm.' mm' : null,
                            'Casing' => $project->casing_requirements,
                            'Pump capacity' => $project->pump_capacity,
                            'GPS' => $project->latitude ? $project->latitude.', '.$project->longitude : null,
                            'Actual completion' => $project->actual_completion_date?->format('d M Y'),
                            'Contingency' => MoneyMath::format((string) $project->contingency),
                        ] as $label => $value)
                            <div><dt class="text-xs text-zinc-500">{{ $label }}</dt><dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $value ?: '—' }}</dd></div>
                        @endforeach
                    </dl>
                    @foreach (['site_details' => 'Site details', 'scope' => 'Scope', 'deliverables' => 'Deliverables', 'special_requirements' => 'Special requirements'] as $field => $label)
                        @if ($project->{$field})
                            <div><div class="text-xs text-zinc-500">{{ $label }}</div><p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $project->{$field} }}</p></div>
                        @endif
                    @endforeach
                </flux:card>

                <flux:card class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Recent activity</flux:heading>
                        <flux:button size="xs" variant="ghost" wire:click="$set('tab', 'activity')">See all</flux:button>
                    </div>
                    @include('projects.partials.activity', ['activities' => $activities])
                </flux:card>
            </div>

            <div class="space-y-6">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">Schedule health</flux:heading>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-zinc-500">Next milestone</span><span class="font-medium">{{ $p['next_milestone']?->name ?? 'All done' }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Delayed milestones</span><span class="font-mono {{ $p['delayed']->count() ? 'text-rose-600' : '' }}">{{ $p['delayed']->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Overdue tasks</span><span class="font-mono {{ $p['overdue_tasks'] ? 'text-rose-600' : '' }}">{{ $p['overdue_tasks'] }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Blocked tasks</span><span class="font-mono {{ $p['blocked_tasks'] ? 'text-amber-600' : '' }}">{{ $p['blocked_tasks'] }}</span></div>
                    </div>
                    @foreach ($p['delayed']->take(4) as $m)
                        <div class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ $m->name }} — planned {{ $m->planned_date->format('d M') }}</div>
                    @endforeach
                </flux:card>

                <flux:card class="space-y-3">
                    <flux:heading size="sm">Cash vs cost</flux:heading>
                    <div class="space-y-2 text-sm">
                        @foreach ([
                            'Approved bills' => $f['expenses_approved'],
                            'Paid' => $f['paid'],
                            'Unpaid approved' => $f['unpaid_approved'],
                            'Awaiting approval' => $f['expenses_pending'],
                            'Stock on site (not cost)' => $f['stock_value'],
                            'Committed (open orders)' => $f['committed'],
                        ] as $label => $amount)
                            <div class="flex justify-between"><span class="text-zinc-500">{{ $label }}</span><span class="font-mono">{{ MoneyMath::format($amount) }}</span></div>
                        @endforeach
                    </div>
                </flux:card>
            </div>
        </div>
    @endif

    {{-- ═══ Child tabs ═══ --}}
    @switch($tab)
        @case('milestones') <livewire:projects.tabs.milestones :project="$project" :key="'tab-milestones'" /> @break
        @case('progress') <livewire:projects.tabs.progress :project="$project" :key="'tab-progress'" /> @break
        @case('tasks') <livewire:projects.tabs.tasks :project="$project" :key="'tab-tasks'" /> @break
        @case('expenses') <livewire:projects.tabs.expenses :project="$project" :key="'tab-expenses'" /> @break
        @case('materials') <livewire:projects.tabs.materials :project="$project" :key="'tab-materials'" /> @break
        @case('resources') <livewire:projects.tabs.resources :project="$project" :key="'tab-resources'" /> @break
        @case('technical') <livewire:projects.tabs.technical :project="$project" :key="'tab-technical'" /> @break
        @case('closure') <livewire:projects.tabs.closure :project="$project" :key="'tab-closure'" /> @break
    @endswitch

    {{-- ═══ Financials ═══ --}}
    @if ($tab === 'financials')
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="sm">Budget vs actual</flux:heading>
                    @can('reviseBudget', $project)
                        <flux:button size="sm" icon="pencil-square" wire:click="startBudgetRevision">Revise budget</flux:button>
                    @endcan
                </div>
                @php
                    $rows = [
                        ['Original approved budget', $f['original_budget'], false],
                        ['Approved variations', $f['variations'], false],
                        ['Current approved budget', $f['current_budget'], true],
                        ['Materials consumed & wasted', $f['material_cost'], false],
                        ['Labour', $f['labour_cost'], false],
                        ['Equipment', $f['equipment_cost'], false],
                        ['Other direct expenses', $f['direct_expenses'], false],
                        ['Actual project cost', $f['actual_cost'], true],
                        ['Committed (open purchase orders)', $f['committed'], false],
                        ['Estimate to complete', $f['estimate_to_complete'], false],
                        ['Forecast final cost', $f['forecast_final'], true],
                        ['Remaining budget', $f['remaining_budget'], false],
                        ['Budget variance (budget − forecast)', $f['variance'], true],
                        ['Contingency remaining', $f['contingency_remaining'], false],
                    ];
                @endphp
                <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
                    @foreach ($rows as [$label, $amount, $strong])
                        <div class="flex justify-between py-2 {{ $strong ? 'font-semibold text-zinc-900 dark:text-zinc-100' : '' }}">
                            <dt class="{{ $strong ? '' : 'text-zinc-500' }}">{{ $label }}</dt>
                            <dd class="font-mono {{ MoneyMath::isNegative($amount) ? 'text-rose-600' : '' }}">{{ MoneyMath::format($amount) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div>
                    <div class="flex justify-between text-xs text-zinc-500"><span>Budget utilisation</span><span>{{ $f['utilization'] }}%</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
                        <div class="h-full rounded-full {{ $f['utilization'] >= 100 ? 'bg-rose-500' : ($f['utilization'] >= 75 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, $f['utilization']) }}%"></div>
                    </div>
                </div>
            </flux:card>

            <div class="space-y-6">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">Cost by category</flux:heading>
                    @forelse ($f['by_category'] as $cat => $amount)
                        <div class="flex justify-between text-sm"><span class="text-zinc-500">{{ config("projects.expense_categories.{$cat}", $cat) }}</span><span class="font-mono">{{ MoneyMath::format($amount) }}</span></div>
                    @empty
                        <flux:text class="text-sm">No approved direct expenses yet.</flux:text>
                    @endforelse
                </flux:card>
                <flux:card class="space-y-3">
                    <flux:heading size="sm">Cost by stage</flux:heading>
                    @forelse ($f['by_stage'] as $stage => $amount)
                        <div class="flex justify-between text-sm"><span class="text-zinc-500">{{ config("projects.stages.{$stage}", ucfirst($stage)) }}</span><span class="font-mono">{{ MoneyMath::format($amount) }}</span></div>
                    @empty
                        <flux:text class="text-sm">No approved direct expenses yet.</flux:text>
                    @endforelse
                </flux:card>
                <flux:callout icon="information-circle" color="sky">
                    <flux:callout.text>
                        Stock still on site ({{ MoneyMath::format($f['stock_value']) }}) and bills marked as settlements are not counted as cost — materials become cost when consumed or wasted, so nothing is counted twice.
                    </flux:callout.text>
                </flux:callout>
            </div>
        </div>
    @endif

    {{-- ═══ Activity ═══ --}}
    @if ($tab === 'activity')
        <flux:card class="space-y-3">
            <flux:heading size="sm">Activity log</flux:heading>
            <flux:text class="text-sm">Every change, approval and stage move on this project — who, what and when.</flux:text>
            @include('projects.partials.activity', ['activities' => $activities])
        </flux:card>
    @endif

    {{-- Stage change --}}
    <flux:modal name="stage-change" class="w-full max-w-md">
        <form wire:submit="changeStage" class="space-y-5">
            <div>
                <flux:heading size="lg">Move to {{ config("projects.stages.{$toStage}", '') }}?</flux:heading>
                <flux:subheading>The change is recorded in the project history.</flux:subheading>
            </div>
            <flux:textarea wire:model="stageNote" label="Note (optional)" rows="2" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Move stage</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Budget revision --}}
    <flux:modal name="revise-budget" class="w-full max-w-md">
        <form wire:submit="reviseBudget" class="space-y-5">
            <div>
                <flux:heading size="lg">Revise budget</flux:heading>
                <flux:subheading>Original budget {{ MoneyMath::format((string) $project->original_budget) }} stays on record.</flux:subheading>
            </div>
            <flux:input type="number" step="0.01" min="0" wire:model="newBudget" label="New approved budget" />
            <flux:textarea wire:model="budgetReason" label="Reason / approved variation" rows="2" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save revision</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
