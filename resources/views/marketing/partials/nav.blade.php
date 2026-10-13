@php
    $links = [
        ['Services', route('marketing.services'), 'marketing.services'],
        ['Pricing', route('marketing.pricing'), 'marketing.pricing'],
        ['How it works', route('home').'#how-it-works', null],
        ['About', route('marketing.about'), 'marketing.about'],
        ['Contact', route('marketing.contact'), 'marketing.contact'],
    ];
@endphp

<header x-data="{ open: false }" x-on:keydown.escape.window="open = false"
    class="sticky top-0 z-50 border-b border-navy/5 bg-white/80 backdrop-blur-lg dark:border-white/10 dark:bg-navy-deep/80">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8">
        @include('marketing.partials.logo')

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
            @foreach ($links as [$label, $href, $routeName])
                @php $current = $routeName && request()->routeIs($routeName); @endphp
                <a href="{{ $href }}" @if ($current) aria-current="page" @endif
                    class="rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-aqua/10 hover:text-teal dark:hover:text-light-aqua {{ $current ? 'text-teal dark:text-light-aqua' : 'text-zinc-600 dark:text-zinc-300' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <button type="button" x-on:click="$flux.dark = ! $flux.dark" aria-label="Toggle dark mode"
                class="flex size-10 items-center justify-center rounded-xl text-zinc-500 transition hover:bg-aqua/10 hover:text-teal dark:text-zinc-400 dark:hover:text-light-aqua">
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>

            @auth
                <a href="{{ route('dashboard') }}" class="btn-mk btn-mk-primary hidden py-2.5 sm:inline-flex">Go to dashboard</a>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-navy transition hover:text-teal sm:inline-flex dark:text-white dark:hover:text-light-aqua">
                    Sign in
                </a>
                <a href="{{ route('access.request') }}" class="btn-mk btn-mk-primary hidden py-2.5 sm:inline-flex">Get started</a>
            @endauth

            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-controls="mobile-menu" aria-label="Menu"
                class="flex size-10 items-center justify-center rounded-xl text-navy transition hover:bg-aqua/10 lg:hidden dark:text-white">
                <flux:icon.bars-3 x-show="! open" class="size-6" />
                <flux:icon.x-mark x-show="open" x-cloak class="size-6" />
            </button>
        </div>
    </div>

    <div id="mobile-menu" x-show="open" x-cloak x-transition.opacity
        class="border-t border-navy/5 bg-white px-5 pb-6 pt-3 lg:hidden dark:border-white/10 dark:bg-navy-deep">
        <nav class="grid gap-1" aria-label="Mobile">
            @foreach ($links as [$label, $href, $routeName])
                <a href="{{ $href }}" x-on:click="open = false"
                    class="rounded-xl px-3 py-3 text-base font-medium text-navy transition hover:bg-aqua/10 dark:text-white">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="mt-4 grid gap-3 sm:hidden">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-mk btn-mk-primary">Go to dashboard</a>
            @else
                <a href="{{ route('access.request') }}" class="btn-mk btn-mk-primary">Get started</a>
                <a href="{{ route('login') }}" class="btn-mk btn-mk-secondary">Sign in</a>
            @endauth
        </div>
    </div>
</header>
