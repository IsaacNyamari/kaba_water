{{-- Decorative income-statement summary. --}}
@php
    $lines = [
        ['4000 Water revenue', '612,000.00', false],
        ['4100 Connection fees', '36,000.00', false],
        ['5000 Water purchases', '195,000.00', true],
        ['5100 Salaries', '144,000.00', true],
        ['5200 Maintenance', '23,100.00', true],
    ];
@endphp

<div class="mk-card p-5 sm:p-6" aria-hidden="true">
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-navy dark:text-white">Income statement</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">1 Jul – 30 Sep 2026</p>
        </div>
        <span class="inline-flex items-center gap-1 rounded-lg border border-teal/20 px-2.5 py-1.5 text-xs font-semibold text-teal dark:border-white/15 dark:text-light-aqua">
            <flux:icon.arrow-down-tray variant="micro" class="size-3.5" /> Export
        </span>
    </div>

    <dl class="mt-5 space-y-2.5 text-sm">
        @foreach ($lines as [$label, $amount, $expense])
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                <dd class="font-mono {{ $expense ? 'text-zinc-500 dark:text-zinc-400' : 'text-navy dark:text-zinc-100' }}">{{ $expense ? '('.$amount.')' : $amount }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="mt-5 flex items-center justify-between gap-4 rounded-xl bg-navy px-4 py-3 dark:bg-navy-deep">
        <p class="text-sm font-semibold text-white">Net income</p>
        <p class="font-mono text-lg font-semibold text-light-aqua">285,900.00</p>
    </div>

    <div class="mt-3 flex items-center gap-2 text-xs text-teal dark:text-light-aqua">
        <flux:icon.scale variant="mini" class="size-4" />
        Balance sheet balances: assets = liabilities + equity
    </div>
</div>
