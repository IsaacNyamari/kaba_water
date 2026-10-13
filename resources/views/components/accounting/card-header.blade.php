@props(['icon' => 'calculator', 'title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-4 flex-wrap']) }}>
    <div class="flex items-center gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
            <flux:icon :name="$icon" class="size-4" />
        </div>
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($subtitle)
                <flux:subheading>{{ $subtitle }}</flux:subheading>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex items-center gap-2 flex-wrap">{{ $actions }}</div>
    @endisset
</div>
