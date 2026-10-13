{{-- Decorative phone showing photo capture of a meter dial. --}}
<div class="mx-auto w-full max-w-xs rounded-[2rem] border border-teal/10 bg-navy p-3 shadow-aqua-lg dark:border-white/10" aria-hidden="true">
    <div class="rounded-[1.5rem] bg-navy-deep p-4">
        <div class="flex items-center justify-between text-xs text-zinc-400">
            <span>Scan meter</span>
            <span class="inline-flex items-center gap-1 text-light-aqua"><span class="size-1.5 rounded-full bg-light-aqua"></span> Camera on</span>
        </div>

        <div class="relative mt-4 flex aspect-square items-center justify-center rounded-2xl bg-linear-to-br from-navy-mid to-navy">
            <span class="absolute left-3 top-3 size-6 rounded-tl-lg border-l-2 border-t-2 border-aqua"></span>
            <span class="absolute right-3 top-3 size-6 rounded-tr-lg border-r-2 border-t-2 border-aqua"></span>
            <span class="absolute bottom-3 left-3 size-6 rounded-bl-lg border-b-2 border-l-2 border-aqua"></span>
            <span class="absolute bottom-3 right-3 size-6 rounded-br-lg border-b-2 border-r-2 border-aqua"></span>

            <div class="flex size-36 flex-col items-center justify-center rounded-full border-4 border-zinc-300/80 bg-zinc-100">
                <span class="text-[0.6rem] font-semibold uppercase tracking-widest text-zinc-500">m³</span>
                <span class="mt-1 rounded-md bg-navy px-2 py-1 font-mono text-base font-semibold tracking-widest text-white">048263</span>
                <span class="mt-2 h-1 w-10 rounded-full bg-rose-400"></span>
            </div>

            <span class="absolute inset-x-6 top-1/2 h-0.5 bg-linear-to-r from-transparent via-light-aqua to-transparent"></span>
        </div>

        <div class="mt-4 rounded-xl bg-white/5 p-3">
            <p class="text-xs text-zinc-400">Reading detected</p>
            <div class="mt-1 flex items-center justify-between">
                <p class="font-mono text-xl font-semibold text-white">48,263</p>
                <span class="rounded-lg bg-aqua px-3 py-1.5 text-xs font-semibold text-navy">Confirm</span>
            </div>
        </div>
    </div>
</div>
