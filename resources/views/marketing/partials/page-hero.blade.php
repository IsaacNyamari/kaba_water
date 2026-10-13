{{-- Navy hero used by the inner pages. --}}
<section class="relative isolate overflow-hidden bg-linear-to-br from-navy via-navy-mid to-teal">
    <div class="absolute -top-40 left-1/2 -z-10 size-[42rem] -translate-x-1/2 rounded-full bg-radial from-aqua/30 to-transparent to-70% blur-2xl" aria-hidden="true"></div>
    <div class="mx-auto max-w-3xl px-5 py-20 text-center sm:px-8 lg:py-24">
        @isset($eyebrow)
            <p class="text-sm font-semibold uppercase tracking-widest text-light-aqua">{{ $eyebrow }}</p>
        @endisset
        <h1 class="mt-3 text-4xl font-semibold tracking-tight text-balance text-white sm:text-5xl">{{ $title }}</h1>
        @isset($lead)
            <p class="mt-5 text-lg text-pretty text-zinc-200">{{ $lead }}</p>
        @endisset
    </div>
</section>
