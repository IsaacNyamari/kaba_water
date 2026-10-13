{{-- Decorative tiered-tariff card. --}}
<div class="mk-card p-5" aria-hidden="true">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-navy dark:text-white">Domestic tariff</p>
        <span class="rounded-full bg-aqua-mist px-2.5 py-1 text-xs font-semibold text-teal dark:bg-aqua/15 dark:text-light-aqua">3 tiers</span>
    </div>
    <ul class="mt-4 space-y-3 text-sm">
        @foreach ([['0 – 6 m³', '90', 'w-1/3'], ['7 – 20 m³', '120', 'w-2/3'], ['Over 20 m³', '150', 'w-full']] as [$band, $rate, $width])
            <li>
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $band }}</span>
                    <span class="font-mono font-semibold text-navy dark:text-zinc-100">KES {{ $rate }} / m³</span>
                </div>
                <div class="mt-1.5 h-1.5 rounded-full bg-teal/10 dark:bg-white/10">
                    <div class="h-1.5 rounded-full bg-linear-to-r from-aqua to-teal {{ $width }}"></div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
