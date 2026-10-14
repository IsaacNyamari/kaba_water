<?php

use App\Models\Project;
use App\Models\ProjectMilestone;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public ?int $editingId = null;
    public string $name = '';
    public string $weight = '5';
    public string $planned_date = '';
    public bool $is_mandatory = true;

    public ?int $waivingId = null;
    public string $waiver_reason = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    protected function milestone(?int $id): ProjectMilestone
    {
        $this->authorize('update', $this->project);

        return $this->project->milestones()->findOrFail($id);
    }

    public function startAdd(): void
    {
        $this->authorize('update', $this->project);
        $this->reset('editingId', 'name', 'planned_date');
        $this->weight = '5';
        $this->is_mandatory = true;
        $this->resetValidation();
        Flux::modal('milestone')->show();
    }

    public function startEdit(int $id): void
    {
        $m = $this->milestone($id);
        $this->editingId = $m->id;
        $this->name = $m->name;
        $this->weight = (string) $m->weight;
        $this->planned_date = $m->planned_date?->toDateString() ?? '';
        $this->is_mandatory = $m->is_mandatory;
        $this->resetValidation();
        Flux::modal('milestone')->show();
    }

    public function save(): void
    {
        $this->authorize('update', $this->project);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:200'],
            'weight' => ['required', 'integer', 'min:0', 'max:1000'],
            'planned_date' => ['nullable', 'date'],
            'is_mandatory' => ['boolean'],
        ]);
        $data['planned_date'] = $data['planned_date'] ?: null;

        DB::transaction(function () use ($data) {
            if ($this->editingId) {
                $m = $this->milestone($this->editingId);
                $m->update($data);
                $this->project->log('milestone', "Milestone updated: {$m->name}", ['milestone_id' => $m->id] + $m->getChanges());
            } else {
                $m = $this->project->milestones()->create($data + ['sort_order' => (int) $this->project->milestones()->max('sort_order') + 1]);
                $this->project->log('milestone', "Milestone added: {$m->name}", ['milestone_id' => $m->id]);
            }
        });

        Flux::modal('milestone')->close();
        Flux::toast(variant: 'success', text: 'Milestone saved.');
        $this->dispatch('project-changed');
    }

    public function complete(int $id): void
    {
        $m = $this->milestone($id);
        $m->update(['completed_at' => now(), 'completed_by' => auth()->id()]);
        $this->project->log('milestone', "Milestone completed: {$m->name}", ['milestone_id' => $m->id]);
        Flux::toast(variant: 'success', heading: 'Milestone completed', text: $m->name);
        $this->dispatch('project-changed');
    }

    public function reopen(int $id): void
    {
        $m = $this->milestone($id);
        $m->update(['completed_at' => null, 'completed_by' => null, 'is_waived' => false, 'waiver_reason' => null]);
        $this->project->log('milestone', "Milestone reopened: {$m->name}", ['milestone_id' => $m->id]);
        Flux::toast(text: "{$m->name} reopened.");
        $this->dispatch('project-changed');
    }

    public function startWaive(int $id): void
    {
        $this->waivingId = $this->milestone($id)->id;
        $this->waiver_reason = '';
        $this->resetValidation();
        Flux::modal('waive')->show();
    }

    public function waive(): void
    {
        $m = $this->milestone($this->waivingId);
        $this->validate(['waiver_reason' => ['required', 'string', 'max:255']], [], ['waiver_reason' => 'reason']);
        $m->update(['is_waived' => true, 'waiver_reason' => $this->waiver_reason]);
        $this->project->log('milestone', "Milestone waived: {$m->name} — {$this->waiver_reason}", ['milestone_id' => $m->id]);
        Flux::modal('waive')->close();
        Flux::toast(text: "{$m->name} waived.");
        $this->dispatch('project-changed');
    }

    public function delete(int $id): void
    {
        $m = $this->milestone($id);
        abort_if($m->completed_at !== null, 422, 'Completed milestones are kept.');
        $this->project->log('milestone', "Milestone removed: {$m->name}", ['milestone_id' => $m->id]);
        $m->delete();
        Flux::toast(text: 'Milestone removed.');
        $this->dispatch('project-changed');
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">Milestones</flux:heading>
            <flux:text class="text-sm">Progress is the share of milestone weight done or waived. Total weight {{ $project->milestones->sum('weight') }}.</flux:text>
        </div>
        @can('update', $project)
            <flux:button variant="primary" icon="plus" wire:click="startAdd">Add milestone</flux:button>
        @endcan
    </div>

    <div class="app-card divide-y divide-zinc-100 dark:divide-white/10">
        @forelse ($project->milestones()->with('completer')->get() as $m)
            <div wire:key="ms-{{ $m->id }}" class="flex flex-wrap items-center gap-3 p-4">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold
                    {{ $m->completed_at ? 'bg-emerald-500 text-white' : ($m->is_waived ? 'bg-zinc-300 text-zinc-700 dark:bg-white/20 dark:text-zinc-200' : ($m->isOverdue() ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' : 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300')) }}">
                    @if ($m->completed_at) <flux:icon.check variant="micro" /> @else {{ $loop->iteration }} @endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $m->name }}</span>
                        <flux:badge size="sm" color="zinc">weight {{ $m->weight }}</flux:badge>
                        @unless ($m->is_mandatory) <flux:badge size="sm" color="sky">optional</flux:badge> @endunless
                        @if ($m->is_waived) <flux:badge size="sm" color="zinc">Waived</flux:badge> @endif
                        @if ($m->isOverdue()) <flux:badge size="sm" color="rose" icon="clock">Delayed</flux:badge> @endif
                    </div>
                    <p class="text-xs text-zinc-500">
                        Planned {{ $m->planned_date?->format('d M Y') ?? '—' }}
                        @if ($m->completed_at) · done {{ $m->completed_at->format('d M Y') }} by {{ $m->completer?->name ?? '—' }} @endif
                        @if ($m->is_waived) · {{ $m->waiver_reason }} @endif
                    </p>
                </div>
                @can('update', $project)
                    <div class="flex gap-1">
                        @if ($m->isSettled())
                            <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="reopen({{ $m->id }})">Reopen</flux:button>
                        @else
                            <flux:button size="sm" icon="check" wire:click="complete({{ $m->id }})">Complete</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="startWaive({{ $m->id }})">Waive</flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $m->id }})" />
                        @unless ($m->completed_at)
                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600" wire:click="delete({{ $m->id }})" wire:confirm="Remove this milestone?" />
                        @endunless
                    </div>
                @endcan
            </div>
        @empty
            <p class="p-6 text-sm text-zinc-500">No milestones yet.</p>
        @endforelse
    </div>

    <flux:modal name="milestone" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Edit milestone' : 'Add milestone' }}</flux:heading>
            <flux:input wire:model="name" label="Name" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="number" wire:model="weight" label="Weight" description="Share of overall progress." />
                <flux:input type="date" wire:model="planned_date" label="Planned date" />
            </div>
            <flux:checkbox wire:model="is_mandatory" label="Mandatory before closure" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="waive" class="w-full max-w-md">
        <form wire:submit="waive" class="space-y-5">
            <div>
                <flux:heading size="lg">Waive milestone</flux:heading>
                <flux:subheading>A waived milestone counts as settled for progress and closure. Give the reason.</flux:subheading>
            </div>
            <flux:input wire:model="waiver_reason" label="Reason" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Waive</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
