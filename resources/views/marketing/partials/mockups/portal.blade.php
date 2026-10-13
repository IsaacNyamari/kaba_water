{{-- Decorative client-portal view. --}}
<div class="mk-card overflow-hidden" aria-hidden="true">
    <div class="bg-linear-to-br from-navy via-navy-mid to-teal p-5 sm:p-6">
        <p class="text-xs text-zinc-300">Good morning, Amina</p>
        <p class="mt-3 text-xs uppercase tracking-widest text-light-aqua">Balance due</p>
        <p class="mt-1 font-mono text-3xl font-semibold text-white">KES 1,700.00</p>
        <p class="mt-1 text-xs text-zinc-300">Due 28 Oct · Meter KW-00231</p>
    </div>

    <div class="grid grid-cols-2 gap-3 p-5 sm:p-6">
        <div class="rounded-xl bg-aqua-tint p-3 dark:bg-white/5">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">This month</p>
            <p class="mt-1 font-mono text-lg font-semibold text-navy dark:text-white">14 m³</p>
        </div>
        <div class="rounded-xl bg-aqua-tint p-3 dark:bg-white/5">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Last month</p>
            <p class="mt-1 font-mono text-lg font-semibold text-navy dark:text-white">12 m³</p>
        </div>

        <div class="col-span-2 space-y-2">
            @foreach ([['INV-2026-0418', 'Sent'], ['INV-2026-0377', 'Paid'], ['INV-2026-0331', 'Paid']] as [$number, $status])
                <div class="flex items-center justify-between rounded-xl border border-teal/10 px-3 py-2.5 dark:border-white/10">
                    <span class="font-mono text-xs font-semibold text-navy dark:text-zinc-100">{{ $number }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $status === 'Paid' ? 'bg-aqua-mist text-teal dark:bg-aqua/15 dark:text-light-aqua' : 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' }}">{{ $status }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
