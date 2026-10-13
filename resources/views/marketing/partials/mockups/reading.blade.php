{{-- Decorative meter-reading card. --}}
<div class="mk-card p-5" aria-hidden="true">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex size-9 items-center justify-center rounded-xl bg-aqua-mist text-teal dark:bg-aqua/15 dark:text-light-aqua">
                <flux:icon.beaker variant="mini" class="size-5" />
            </span>
            <div>
                <p class="text-sm font-semibold text-navy dark:text-white">Meter KW-00231</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Read today, 9:42 am</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full bg-aqua-mist px-2.5 py-1 text-xs font-semibold text-teal dark:bg-aqua/15 dark:text-light-aqua">
            <flux:icon.check variant="micro" class="size-3.5" /> Verified
        </span>
    </div>

    <div class="mt-4 flex items-center justify-between gap-4">
        <div class="flex gap-1 font-mono text-lg font-semibold">
            @foreach (['0', '4', '8', '2', '6'] as $digit)
                <span class="flex h-9 w-7 items-center justify-center rounded-md bg-navy text-white dark:bg-navy-deep">{{ $digit }}</span>
            @endforeach
            <span class="flex h-9 w-7 items-center justify-center rounded-md bg-aqua text-navy">3</span>
        </div>
        <div class="text-right">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Used this month</p>
            <p class="font-mono text-base font-semibold text-navy dark:text-white">14 m³</p>
        </div>
    </div>

    <div class="mt-4 flex h-12 items-end gap-1.5">
        @foreach (['h-5', 'h-7', 'h-6', 'h-9', 'h-8', 'h-10', 'h-7', 'h-12'] as $i => $height)
            <span class="flex-1 rounded-t-md {{ $height }} {{ $loop->last ? 'bg-aqua' : 'bg-teal/20 dark:bg-white/15' }}"></span>
        @endforeach
    </div>
</div>
