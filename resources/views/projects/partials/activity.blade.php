@php
    $icons = [
        'created' => 'sparkles', 'updated' => 'pencil-square', 'status_changed' => 'arrow-path',
        'budget_revised' => 'banknotes', 'budget_alert' => 'exclamation-triangle', 'closed' => 'check-badge', 'reopened' => 'arrow-uturn-left',
        'material' => 'cube', 'progress_report' => 'clipboard-document-list', 'milestone' => 'flag', 'task' => 'check-circle',
        'resource' => 'user-group', 'technical' => 'beaker', 'document' => 'paper-clip',
    ];
@endphp

<ol class="space-y-3">
    @forelse ($activities as $a)
        <li wire:key="activity-{{ $a->id }}" class="flex gap-3">
            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                <flux:icon :name="$icons[$a->event] ?? (str_starts_with($a->event, 'expense') ? 'receipt-percent' : 'bolt')" variant="micro" />
            </span>
            <div class="min-w-0 text-sm">
                <p class="text-zinc-800 dark:text-zinc-200">{{ $a->description }}</p>
                <p class="text-xs text-zinc-500">{{ $a->user?->name ?? 'System' }} · {{ $a->created_at->format('d M Y, H:i') }}</p>
            </div>
        </li>
    @empty
        <li class="text-sm text-zinc-500">Nothing recorded yet.</li>
    @endforelse
</ol>
