{{-- Decorative payments list. --}}
@php
    $rows = [
        ['Amina Njoroge', 'M-Pesa · SJK4X7Q2LM', '1,700.00', 'device-phone-mobile'],
        ['Kilele Court Flats', 'Bank transfer', '18,450.00', 'building-library'],
        ['Peter Otieno', 'Cash · receipt 0092', '950.00', 'banknotes'],
    ];
@endphp

<div class="mk-card p-5 sm:p-6" aria-hidden="true">
    <div class="flex items-center justify-between gap-4">
        <p class="text-sm font-semibold text-navy dark:text-white">Payments today</p>
        <p class="font-mono text-sm font-semibold text-teal dark:text-light-aqua">KES 21,100.00</p>
    </div>

    <ul class="mt-4 divide-y divide-teal/10 dark:divide-white/10">
        @foreach ($rows as [$name, $detail, $amount, $icon])
            <li class="flex items-center gap-3 py-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-aqua-mist text-teal dark:bg-aqua/15 dark:text-light-aqua">
                    <flux:icon :name="$icon" variant="mini" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-navy dark:text-white">{{ $name }}</p>
                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $detail }}</p>
                </div>
                <p class="font-mono text-sm font-semibold text-navy dark:text-zinc-100">{{ $amount }}</p>
            </li>
        @endforeach
    </ul>

    <div class="mt-2 flex items-center gap-2 rounded-xl bg-aqua-tint px-4 py-3 text-xs text-teal dark:bg-white/5 dark:text-light-aqua">
        <flux:icon.check-circle variant="mini" class="size-4 shrink-0" />
        Invoice INV-2026-0418 marked as paid
    </div>
</div>
