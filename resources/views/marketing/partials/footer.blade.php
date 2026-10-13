@php
    $columns = [
        'Product' => [
            ['Services', route('marketing.services')],
            ['Pricing', route('marketing.pricing')],
            ['How it works', route('home').'#how-it-works'],
            ['Sign in', route('login')],
        ],
        'Company' => [
            ['About', route('marketing.about')],
            ['Contact', route('marketing.contact')],
            ['Book a demo', route('marketing.contact')],
        ],
        'Resources' => [
            ['Meter readings', route('marketing.services').'#meter-readings'],
            ['Invoicing & tariffs', route('marketing.services').'#invoicing'],
            ['Financial reports', route('marketing.services').'#reports'],
            ['Pricing FAQ', route('marketing.pricing').'#faq'],
        ],
        'Legal' => [
            ['Terms of service', route('marketing.terms')],
            ['Privacy policy', route('marketing.privacy')],
        ],
    ];
    $social = config('marketing.social');
@endphp

<footer class="bg-navy-deep text-zinc-300">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8">
        <div class="grid gap-12 lg:grid-cols-[1.3fr_2fr]">
            <div>
                @include('marketing.partials.logo', ['tone' => 'light'])
                <p class="mt-4 max-w-xs text-sm text-pretty text-zinc-400">{{ config('marketing.tagline') }}</p>

                <div class="mt-6 flex gap-2">
                    <a href="{{ $social['x'] }}" aria-label="Kaba Water on X" class="flex size-10 items-center justify-center rounded-xl bg-white/5 text-zinc-300 transition hover:bg-aqua hover:text-navy">
                        <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2" aria-hidden="true"><path stroke-linecap="round" d="M5 5l14 14M19 5L5 19" /></svg>
                    </a>
                    <a href="{{ $social['facebook'] }}" aria-label="Kaba Water on Facebook" class="flex size-10 items-center justify-center rounded-xl bg-white/5 text-zinc-300 transition hover:bg-aqua hover:text-navy">
                        <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.5 4.5h-2a3 3 0 0 0-3 3V20M7.5 11.5h7" /></svg>
                    </a>
                    <a href="{{ $social['linkedin'] }}" aria-label="Kaba Water on LinkedIn" class="flex size-10 items-center justify-center rounded-xl bg-white/5 text-zinc-300 transition hover:bg-aqua hover:text-navy">
                        <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 10v8M6 6v.01M11 18v-8M11 13.5a3 3 0 0 1 6 0V18" /></svg>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                @foreach ($columns as $heading => $items)
                    <div>
                        <h3 class="text-sm font-semibold text-white">{{ $heading }}</h3>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($items as [$label, $href])
                                <li><a href="{{ $href }}" class="text-zinc-400 transition hover:text-light-aqua">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-14 flex flex-col gap-3 border-t border-white/10 pt-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
            <p>Made in Nairobi for water vendors everywhere.</p>
        </div>
    </div>
</footer>
