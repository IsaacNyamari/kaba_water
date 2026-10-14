<?php

use App\Models\Project;
use App\Services\Projects\ProjectClosure;
use App\Support\Money\MoneyMath;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public string $closingNotes = '';
    public string $reopenReason = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function close(): void
    {
        $this->authorize('close', $this->project);
        $this->validate(['closingNotes' => ['nullable', 'string', 'max:2000']]);

        app(ProjectClosure::class)->close($this->project, $this->closingNotes ?: null);

        Flux::modal('close-project')->close();
        Flux::toast(variant: 'success', heading: 'Project closed', text: 'The final statement is saved.');
        $this->dispatch('project-changed');
    }

    public function reopen(): void
    {
        $this->authorize('close', $this->project);
        $this->validate(['reopenReason' => ['required', 'string', 'max:500']], [], ['reopenReason' => 'reason']);

        app(ProjectClosure::class)->reopen($this->project, $this->reopenReason);

        Flux::modal('reopen-project')->close();
        Flux::toast(variant: 'warning', heading: 'Project reopened', text: 'Recorded in the activity log.');
        $this->dispatch('project-changed');
    }

    public function with(): array
    {
        $closure = app(ProjectClosure::class);
        $project = $this->project->fresh();

        return [
            'checklist' => $closure->checklist($project),
            'canClose' => $closure->canClose($project),
            'statement' => $project->final_statement ?? $closure->statement($project),
            'isFinal' => $project->final_statement !== null && $project->status === 'completed',
        ];
    }
};
?>

<div class="grid gap-6 lg:grid-cols-2">
    <flux:card class="space-y-4">
        <div>
            <flux:heading size="sm">Closure checklist</flux:heading>
            <flux:text class="text-sm">Every item must pass before the project can be closed and handed over.</flux:text>
        </div>
        <ul class="space-y-2">
            @foreach ($checklist as $item)
                <li wire:key="check-{{ $item['key'] }}" class="flex gap-3 rounded-lg p-2 {{ $item['passed'] ? '' : 'bg-amber-50 dark:bg-amber-500/10' }}">
                    <flux:icon :name="$item['passed'] ? 'check-circle' : 'x-circle'" variant="mini" class="mt-0.5 size-5 shrink-0 {{ $item['passed'] ? 'text-emerald-500' : 'text-amber-500' }}" />
                    <div class="text-sm">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item['label'] }}</p>
                        <p class="text-xs text-zinc-500">{{ $item['detail'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        @can('close', $project)
            @if ($project->status === 'completed')
                <flux:button icon="arrow-uturn-left" x-on:click="$flux.modal('reopen-project').show()">Reopen project</flux:button>
            @elseif (! $project->isClosed())
                <flux:button variant="primary" icon="check-badge" :disabled="! $canClose" x-on:click="$flux.modal('close-project').show()">Close & hand over</flux:button>
            @endif
        @endcan
    </flux:card>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="sm">{{ $isFinal ? 'Final project statement' : 'Statement preview' }}</flux:heading>
            @if ($isFinal)
                <flux:badge color="emerald" icon="lock-closed">Frozen {{ \Illuminate\Support\Carbon::parse($statement['generated_at'])->format('d M Y') }}</flux:badge>
            @endif
        </div>
        <dl class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
            @foreach ([
                'Original budget' => $statement['original_budget'],
                'Approved variations' => $statement['approved_variations'],
                'Current budget' => $statement['current_budget'],
                'Materials' => $statement['material_cost'],
                '… of which wastage' => $statement['wastage_cost'],
                'Labour' => $statement['labour_cost'],
                'Equipment' => $statement['equipment_cost'],
                'Other costs' => $statement['other_costs'],
                'Total final cost' => $statement['total_final_cost'],
                'Variance vs budget' => $statement['variance'],
                'Paid to date' => $statement['paid'],
            ] as $label => $amount)
                <div class="flex justify-between py-2 {{ in_array($label, ['Total final cost', 'Variance vs budget']) ? 'font-semibold' : '' }}">
                    <dt class="text-zinc-600 dark:text-zinc-300">{{ $label }}</dt>
                    <dd class="font-mono {{ MoneyMath::isNegative($amount) ? 'text-rose-600' : '' }}">{{ MoneyMath::format($amount) }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-xs text-zinc-500">Planned depth</span><div class="font-mono">{{ $statement['planned_depth_m'] ?: '—' }} m</div></div>
            <div><span class="text-xs text-zinc-500">Actual depth</span><div class="font-mono">{{ $statement['actual_depth_m'] ?: '—' }} m</div></div>
            <div><span class="text-xs text-zinc-500">Yield</span><div class="font-mono">{{ $statement['technical']['yield_m3_per_hour'] ?? '—' }} m³/h</div></div>
            <div><span class="text-xs text-zinc-500">Final inspection</span><div>{{ $statement['technical']['final_inspection_result'] ?? '—' }}</div></div>
        </div>
        @if (! empty($statement['notes']))
            <p class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-white/5">{{ $statement['notes'] }}</p>
        @endif
    </flux:card>

    <flux:modal name="close-project" class="w-full max-w-md">
        <form wire:submit="close" class="space-y-5">
            <div>
                <flux:heading size="lg">Close {{ $project->reference }}?</flux:heading>
                <flux:subheading>The final statement is frozen and the project becomes read-only. Reopening needs a reason and is logged.</flux:subheading>
            </div>
            <flux:textarea wire:model="closingNotes" label="Closing notes (optional)" rows="3" />
            <flux:error name="close" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Close project</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="reopen-project" class="w-full max-w-md">
        <form wire:submit="reopen" class="space-y-5">
            <div>
                <flux:heading size="lg">Reopen project</flux:heading>
                <flux:subheading>It returns to Commissioning. The previous statement is kept in the activity log.</flux:subheading>
            </div>
            <flux:input wire:model="reopenReason" label="Reason" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Reopen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
