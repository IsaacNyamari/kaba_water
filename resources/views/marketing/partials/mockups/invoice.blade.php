{{-- Decorative invoice preview. --}}
<div class="mk-card p-5 sm:p-6" aria-hidden="true">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-teal dark:text-light-aqua">Invoice</p>
            <p class="mt-1 font-mono text-sm font-semibold text-navy dark:text-white">INV-2026-0418</p>
        </div>
        <span class="rounded-full bg-aqua-mist px-2.5 py-1 text-xs font-semibold text-teal dark:bg-aqua/15 dark:text-light-aqua">Sent</span>
    </div>

    <div class="mt-5 flex items-center gap-3">
        <span class="flex size-9 items-center justify-center rounded-full bg-navy text-xs font-semibold text-white dark:bg-white/10">AN</span>
        <div>
            <p class="text-sm font-semibold text-navy dark:text-white">Amina Njoroge</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Meter KW-00231 · Block C, House 14</p>
        </div>
    </div>

    <dl class="mt-5 space-y-2.5 border-t border-dashed border-teal/20 pt-4 text-sm dark:border-white/10">
        <div class="flex justify-between gap-4">
            <dt class="text-zinc-500 dark:text-zinc-400">First 6 m³ @ 90</dt>
            <dd class="font-mono text-navy dark:text-zinc-100">540.00</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-zinc-500 dark:text-zinc-400">Next 8 m³ @ 120</dt>
            <dd class="font-mono text-navy dark:text-zinc-100">960.00</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-zinc-500 dark:text-zinc-400">Standing charge</dt>
            <dd class="font-mono text-navy dark:text-zinc-100">200.00</dd>
        </div>
    </dl>

    <div class="mt-4 flex items-end justify-between gap-4 rounded-xl bg-aqua-tint px-4 py-3 dark:bg-white/5">
        <div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Total due · 28 Oct</p>
            <p class="font-mono text-xl font-semibold text-navy dark:text-white">KES 1,700.00</p>
        </div>
        <span class="inline-flex items-center gap-1 rounded-lg bg-aqua px-3 py-1.5 text-xs font-semibold text-navy">
            <flux:icon.arrow-down-tray variant="micro" class="size-3.5" /> PDF
        </span>
    </div>
</div>
