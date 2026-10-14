<?php

use App\Models\Project;
use App\Models\ProjectProgressReport;
use App\Services\Projects\ProjectAlerts;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads, WithPagination;

    public Project $project;

    public string $report_date = '';
    public string $stage = '';
    public string $activities_completed = '';
    public string $next_day_plan = '';
    public string $planned_depth_m = '';
    public string $actual_depth_m = '';
    public string $labour_summary = '';
    public string $equipment_summary = '';
    public string $weather = '';
    public string $challenges = '';
    public string $incidents = '';
    public string $risks = '';
    public string $corrective_actions = '';
    public string $progress_percent = '';
    public array $uploads = [];

    public ?int $reviewingId = null;
    public string $pm_remarks = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function startReport(): void
    {
        $this->authorize('report', $this->project);
        $this->reset('activities_completed', 'next_day_plan', 'planned_depth_m', 'actual_depth_m', 'labour_summary', 'equipment_summary',
            'weather', 'challenges', 'incidents', 'risks', 'corrective_actions', 'progress_percent', 'uploads');
        $this->report_date = today()->toDateString();
        $this->stage = $this->project->status;
        $this->resetValidation();
        Flux::modal('report')->show();
    }

    public function submit(): void
    {
        $this->authorize('report', $this->project);
        $data = $this->validate([
            'report_date' => ['required', 'date', 'before_or_equal:today',
                Rule::unique('project_progress_reports', 'report_date')->where('project_id', $this->project->id)],
            'stage' => ['required', Rule::in(array_keys(config('projects.stages')))],
            'activities_completed' => ['required', 'string', 'max:5000'],
            'next_day_plan' => ['nullable', 'string', 'max:5000'],
            'planned_depth_m' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'actual_depth_m' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'labour_summary' => ['nullable', 'string', 'max:2000'],
            'equipment_summary' => ['nullable', 'string', 'max:2000'],
            'weather' => ['nullable', 'string', 'max:100'],
            'challenges' => ['nullable', 'string', 'max:2000'],
            'incidents' => ['nullable', 'string', 'max:2000'],
            'risks' => ['nullable', 'string', 'max:2000'],
            'corrective_actions' => ['nullable', 'string', 'max:2000'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'uploads' => ['array', 'max:10'],
            'uploads.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,heic,mp4,mov,pdf,doc,docx,xls,xlsx'],
        ], ['report_date.unique' => 'There is already a report for this day.']);

        $report = DB::transaction(function () use ($data) {
            $attachments = collect($this->uploads)->map(fn ($file) => [
                'path' => $file->store("projects/{$this->project->id}/reports", 'public'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
            ])->all();

            $report = $this->project->progressReports()->create(collect($data)->except('uploads')
                ->map(fn ($v) => $v === '' ? null : $v)->all() + [
                    'reported_by' => auth()->id(),
                    'attachments' => $attachments ?: null,
                    'status' => 'submitted',
                ]);

            // The deepest depth reported so far is the project's actual depth.
            if ($report->actual_depth_m !== null && (float) $report->actual_depth_m > (float) ($this->project->actual_depth_m ?? 0)) {
                $this->project->update(['actual_depth_m' => $report->actual_depth_m]);
            }

            $this->project->log('progress_report', "Daily report for {$report->report_date->format('d M Y')} submitted", ['report_id' => $report->id]);

            return $report;
        });

        ProjectAlerts::notifyUser($this->project->manager, 'Daily progress report', "{$this->project->reference} · {$report->report_date->format('d M')}", $this->project, 'progress', 'clipboard-document-list');

        Flux::modal('report')->close();
        Flux::toast(variant: 'success', heading: 'Report submitted', text: 'The project manager has been notified.');
        $this->dispatch('project-changed');
    }

    public function startReview(int $id): void
    {
        $this->authorize('update', $this->project);
        $this->reviewingId = $this->project->progressReports()->findOrFail($id)->id;
        $this->pm_remarks = '';
        Flux::modal('review')->show();
    }

    public function approve(): void
    {
        $this->authorize('update', $this->project);
        $report = $this->project->progressReports()->findOrFail($this->reviewingId);
        $this->validate(['pm_remarks' => ['nullable', 'string', 'max:2000']]);

        $report->update(['status' => 'approved', 'pm_remarks' => $this->pm_remarks ?: null, 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $this->project->log('progress_report', "Daily report for {$report->report_date->format('d M Y')} approved", ['report_id' => $report->id]);
        ProjectAlerts::notifyUser($report->reporter, 'Report approved', "{$this->project->reference} · {$report->report_date->format('d M')}", $this->project, 'progress', 'check-circle');

        Flux::modal('review')->close();
        Flux::toast(variant: 'success', text: 'Report approved.');
    }

    public function with(): array
    {
        return [
            'reports' => ProjectProgressReport::query()->where('project_id', $this->project->id)
                ->with(['reporter', 'approver'])->latest('report_date')->paginate(10),
        ];
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">Daily progress reports</flux:heading>
            <flux:text class="text-sm">What happened on site each day, with photos and evidence.</flux:text>
        </div>
        @can('report', $project)
            <flux:button variant="primary" icon="plus" wire:click="startReport">Submit today's report</flux:button>
        @endcan
    </div>

    @forelse ($reports as $r)
        <div wire:key="report-{{ $r->id }}" class="app-card space-y-3 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $r->report_date->format('D, d M Y') }}</span>
                        <flux:badge size="sm" color="blue">{{ config("projects.stages.{$r->stage}") }}</flux:badge>
                        <flux:badge size="sm" :color="$r->status === 'approved' ? 'emerald' : 'amber'">{{ ucfirst($r->status) }}</flux:badge>
                        @if ($r->progress_percent !== null) <flux:badge size="sm" color="zinc">{{ $r->progress_percent }}% reported</flux:badge> @endif
                    </div>
                    <p class="text-xs text-zinc-500">By {{ $r->reporter?->name ?? '—' }}@if ($r->weather) · {{ $r->weather }} @endif</p>
                </div>
                @if ($r->status === 'submitted')
                    @can('update', $project)
                        <flux:button size="sm" icon="check" wire:click="startReview({{ $r->id }})">Review</flux:button>
                    @endcan
                @endif
            </div>

            <div class="grid gap-3 text-sm sm:grid-cols-2">
                <div><div class="text-xs text-zinc-500">Done today</div><p class="whitespace-pre-line">{{ $r->activities_completed }}</p></div>
                @if ($r->next_day_plan) <div><div class="text-xs text-zinc-500">Plan for tomorrow</div><p class="whitespace-pre-line">{{ $r->next_day_plan }}</p></div> @endif
                @if ($r->actual_depth_m !== null || $r->planned_depth_m !== null)
                    <div><div class="text-xs text-zinc-500">Depth (planned / actual)</div><p class="font-mono">{{ $r->planned_depth_m ?? '—' }} m / {{ $r->actual_depth_m ?? '—' }} m</p></div>
                @endif
                @foreach (['labour_summary' => 'Labour', 'equipment_summary' => 'Equipment', 'challenges' => 'Challenges & delays', 'incidents' => 'Incidents', 'risks' => 'Risks', 'corrective_actions' => 'Corrective actions'] as $field => $label)
                    @if ($r->{$field}) <div><div class="text-xs text-zinc-500">{{ $label }}</div><p class="whitespace-pre-line">{{ $r->{$field} }}</p></div> @endif
                @endforeach
            </div>

            @if ($r->attachments)
                <div class="flex flex-wrap gap-2">
                    @foreach ($r->attachments as $file)
                        @if (str_starts_with($file['mime'] ?? '', 'image/'))
                            <a href="{{ Storage::disk('public')->url($file['path']) }}" target="_blank"><img src="{{ Storage::disk('public')->url($file['path']) }}" alt="{{ $file['name'] }}" class="size-20 rounded-lg object-cover ring-1 ring-zinc-200 dark:ring-white/10" /></a>
                        @else
                            <a href="{{ Storage::disk('public')->url($file['path']) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-3 py-2 text-xs dark:bg-white/10"><flux:icon.paper-clip variant="micro" /> {{ $file['name'] }}</a>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ($r->pm_remarks || $r->approved_at)
                <p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <strong>PM:</strong> {{ $r->pm_remarks ?: 'Approved.' }} <span class="text-xs">— {{ $r->approver?->name }}, {{ $r->approved_at?->format('d M H:i') }}</span>
                </p>
            @endif
        </div>
    @empty
        <div class="app-card p-10 text-center text-sm text-zinc-500">No reports yet.</div>
    @endforelse

    {{ $reports->links() }}

    <flux:modal name="report" class="w-full max-w-2xl">
        <form wire:submit="submit" class="space-y-5">
            <div>
                <flux:heading size="lg">Daily progress report</flux:heading>
                <flux:subheading>{{ $project->reference }} · {{ $project->name }}</flux:subheading>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="report_date" label="Date" />
                <flux:select wire:model="stage" label="Current stage">
                    @foreach (config('projects.stages') as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:textarea wire:model="activities_completed" label="Activities completed" rows="3" />
            <flux:textarea wire:model="next_day_plan" label="Work planned for tomorrow" rows="2" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input type="number" step="0.01" wire:model="planned_depth_m" label="Planned depth (m)" />
                <flux:input type="number" step="0.01" wire:model="actual_depth_m" label="Actual depth (m)" />
                <flux:input type="number" wire:model="progress_percent" label="Progress % (estimate)" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:textarea wire:model="labour_summary" label="Labour deployed" rows="2" />
                <flux:textarea wire:model="equipment_summary" label="Equipment deployed" rows="2" />
            </div>
            <flux:input wire:model="weather" label="Weather" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:textarea wire:model="challenges" label="Challenges & delays" rows="2" />
                <flux:textarea wire:model="incidents" label="Incidents" rows="2" />
                <flux:textarea wire:model="risks" label="Risks" rows="2" />
                <flux:textarea wire:model="corrective_actions" label="Corrective actions" rows="2" />
            </div>
            <flux:input type="file" wire:model="uploads" multiple label="Photos, videos & documents" description="Up to 10 files, 20 MB each." />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Submit report</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="review" class="w-full max-w-md">
        <form wire:submit="approve" class="space-y-5">
            <flux:heading size="lg">Approve report</flux:heading>
            <flux:textarea wire:model="pm_remarks" label="PM remarks (optional)" rows="3" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Approve</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
