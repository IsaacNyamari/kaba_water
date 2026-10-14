<?php

use App\Enums\WorkOrderStatus;
use App\Livewire\Concerns\RefreshesOnActivity;
use App\Models\WorkOrder;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * "My jobs" — the work orders assigned to the signed-in team member, with
 * what they need on site (client, phone, address, meter) and one-tap
 * Start / Complete. The client and the office are notified live.
 */
new #[Layout('layouts.vendor'), Title('My jobs')] class extends Component {
    use RefreshesOnActivity;

    #[Url(history: true)]
    public string $show = 'open';

    public ?int $completingId = null;
    public string $resolution = '';

    public function mount(): void
    {
        $this->authorize('vendor.jobs.view');
    }

    #[Computed]
    public function jobs()
    {
        return WorkOrder::query()
            ->with(['client', 'meter'])
            ->where('assigned_to', auth()->id())
            ->when($this->show === 'open', fn ($q) => $q->open(), fn ($q) => $q->whereIn('status', ['completed', 'cancelled']))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('scheduled_at')
            ->latest('id')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function counts(): array
    {
        $mine = WorkOrder::query()->where('assigned_to', auth()->id());

        return [
            'open' => (clone $mine)->open()->count(),
            'urgent' => (clone $mine)->open()->whereIn('priority', ['urgent', 'high'])->count(),
            'done_week' => (clone $mine)->where('status', 'completed')->where('completed_at', '>=', now()->startOfWeek())->count(),
        ];
    }

    protected function myJob(int $id): WorkOrder
    {
        $this->authorize('vendor.jobs.view');

        return WorkOrder::query()->where('assigned_to', auth()->id())->findOrFail($id);
    }

    public function start(int $id): void
    {
        $job = $this->myJob($id);

        if (in_array($job->status, [WorkOrderStatus::Pending, WorkOrderStatus::Assigned], true)) {
            $job->update(['status' => WorkOrderStatus::InProgress, 'started_at' => $job->started_at ?? now()]);
            Flux::toast(variant: 'success', heading: 'Job started', text: "{$job->number} — the client and the office have been told.");
        }
    }

    public function confirmComplete(int $id): void
    {
        $this->completingId = $this->myJob($id)->id;
        $this->resolution = '';
        $this->resetValidation();
        Flux::modal('complete-job')->show();
    }

    public function complete(): void
    {
        $job = $this->myJob($this->completingId);
        $this->validate(['resolution' => ['required', 'string', 'max:2000']], [], ['resolution' => 'what was done']);

        $job->update([
            'status' => WorkOrderStatus::Completed,
            'resolution' => $this->resolution,
            'started_at' => $job->started_at ?? now(),
            'completed_at' => now(),
        ]);

        Flux::modal('complete-job')->close();
        Flux::toast(variant: 'success', heading: 'Job completed', text: "{$job->number} — nice work.");
        $this->reset('completingId', 'resolution');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([['Open jobs', $this->counts['open'], 'clipboard-document-list', 'brand'], ['Urgent / soon', $this->counts['urgent'], 'exclamation-triangle', $this->counts['urgent'] ? 'rose' : 'sky'], ['Done this week', $this->counts['done_week'], 'check-circle', 'emerald']] as [$label, $value, $icon, $tone])
            <div wire:key="job-kpi-{{ $loop->index }}" class="stat-card tone-{{ $tone }} p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</span>
                    <flux:icon :name="$icon" variant="mini" class="size-5 text-zinc-400" />
                </div>
                <div class="mt-2 font-mono text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between gap-3">
        <flux:heading size="lg">My jobs</flux:heading>
        <div class="grid grid-cols-2 gap-1 rounded-xl bg-brand-900/5 p-1 ring-1 ring-brand-900/10 dark:bg-white/10 dark:ring-white/15">
            @foreach (['open' => 'To do', 'done' => 'Done'] as $value => $label)
                <button type="button" wire:click="$set('show', '{{ $value }}')"
                    class="rounded-lg px-4 py-1.5 text-sm font-semibold transition {{ $show === $value ? 'bg-brand-500 text-white shadow-sm dark:bg-brand-400 dark:text-brand-950' : 'text-zinc-600 hover:bg-brand-900/5 dark:text-zinc-300 dark:hover:bg-white/10' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($this->jobs as $job)
            <div wire:key="job-{{ $job->id }}" class="app-card space-y-4 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-zinc-500">{{ $job->number }}</span>
                            <flux:badge size="sm" :color="$job->status->color()">{{ $job->status->label() }}</flux:badge>
                            @if (in_array($job->priority->value, ['urgent', 'high'], true))
                                <flux:badge size="sm" color="rose" icon="exclamation-triangle">{{ $job->priority->label() }}</flux:badge>
                            @endif
                        </div>
                        <h3 class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">{{ $job->title }}</h3>
                        <p class="text-xs text-zinc-500">{{ $job->type->label() }}{{ $job->scheduled_at ? ' · scheduled '.$job->scheduled_at->format('d M, H:i') : '' }}</p>
                    </div>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                        <flux:icon :name="$job->type->icon()" variant="mini" class="size-5" />
                    </span>
                </div>

                @if ($job->description)
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $job->description }}</p>
                @endif

                <dl class="grid gap-2 rounded-xl bg-brand-50/60 p-3 text-sm dark:bg-white/5 sm:grid-cols-2">
                    <div><dt class="text-xs text-zinc-500">Client</dt><dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $job->client?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">Phone</dt><dd>
                        @if ($job->client?->phone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $job->client->phone) }}" class="font-medium text-brand-700 underline-offset-2 hover:underline dark:text-brand-300">{{ $job->client->phone }}</a>
                        @else
                            —
                        @endif
                    </dd></div>
                    <div><dt class="text-xs text-zinc-500">Address</dt><dd>{{ $job->client?->address ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">Meter</dt><dd>{{ $job->meter ? $job->meter->serial.($job->meter->location ? ' · '.$job->meter->location : '') : '—' }}</dd></div>
                </dl>

                @if ($job->resolution)
                    <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <strong>Done:</strong> {{ $job->resolution }}
                    </p>
                @endif

                @if (in_array($job->status->value, ['pending', 'assigned', 'in_progress'], true))
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($job->status->value !== 'in_progress')
                            <flux:button size="sm" icon="play" wire:click="start({{ $job->id }})">Start</flux:button>
                        @endif
                        <flux:button size="sm" variant="primary" icon="check" wire:click="confirmComplete({{ $job->id }})">Complete</flux:button>
                    </div>
                @endif
            </div>
        @empty
            <div class="app-card p-10 text-center lg:col-span-2">
                <flux:icon.check-badge class="mx-auto size-10 text-brand-500" />
                <p class="mt-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $show === 'open' ? 'No jobs right now' : 'Nothing completed yet' }}</p>
                <p class="text-sm text-zinc-500">New jobs appear here the moment they are assigned to you.</p>
            </div>
        @endforelse
    </div>

    <flux:modal name="complete-job" class="w-full max-w-md">
        <form wire:submit="complete" class="space-y-5">
            <div>
                <flux:heading size="lg">Complete this job</flux:heading>
                <flux:subheading>The client sees this note.</flux:subheading>
            </div>
            <flux:textarea wire:model="resolution" label="What was done?" rows="4" placeholder="e.g. Replaced the leaking joint and tested the line." />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Mark complete</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
