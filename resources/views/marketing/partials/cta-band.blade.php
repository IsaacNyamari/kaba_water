<section class="relative isolate overflow-hidden bg-linear-to-br from-navy via-navy-mid to-teal">
    <div class="absolute -bottom-48 right-0 -z-10 size-[36rem] rounded-full bg-radial from-light-aqua/25 to-transparent to-70% blur-2xl" aria-hidden="true"></div>
    <div class="mx-auto max-w-4xl px-5 py-20 text-center sm:px-8 lg:py-24">
        <h2 class="text-3xl font-semibold tracking-tight text-balance text-white sm:text-4xl">
            {{ $title ?? "Send this month's bills before lunch." }}
        </h2>
        <p class="mx-auto mt-4 max-w-xl text-lg text-pretty text-zinc-200">
            {{ $lead ?? 'Set up your clients, meters and tariffs once. Kaba Water takes it from the reading to the receipt.' }}
        </p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="{{ route('access.request') }}" wire:navigate class="btn-mk btn-mk-primary w-full sm:w-auto">
                Start free <flux:icon.arrow-right variant="micro" class="size-4" />
            </a>
            <a href="{{ route('marketing.contact') }}" wire:navigate class="btn-mk btn-mk-on-dark w-full sm:w-auto">Book a demo</a>
        </div>
    </div>
</section>
