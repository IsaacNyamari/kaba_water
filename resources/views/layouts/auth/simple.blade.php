<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased">
        @php
            // A real water photo is used when present; otherwise a drawn water scene.
            $waterPhoto = file_exists(public_path('images/auth-water.jpg')) ? asset('images/auth-water.jpg') : null;

            // Left-hand welcome copy, per page.
            [$heroTitle, $heroText, $consent] = match (true) {
                request()->routeIs('login') => [
                    'Welcome back',
                    'Pick up where you left off: readings waiting for review, invoices to send and payments to match, all in one place.',
                    'By signing in you agree to our',
                ],
                request()->routeIs('access.request') => [
                    'Bring your water business online',
                    'Tell us who you are and we will set up your workspace. Approved requests get a sign-up link by email, usually within a day.',
                    'By requesting access you agree to our',
                ],
                request()->routeIs('access.accept') => [
                    'You are almost in',
                    'Set your password to finish creating your account and start billing every drop.',
                    'By creating an account you agree to our',
                ],
                default => [
                    config('app.name'),
                    'Water billing, made simple: from the meter reading to the invoice to the payment.',
                    'By continuing you agree to our',
                ],
            };

            $social = config('marketing.social', []);
        @endphp

        <div class="relative isolate min-h-svh overflow-hidden bg-brand-50 dark:bg-brand-950">
            {{-- ─── Background ─── --}}
            @if ($waterPhoto)
                <img src="{{ $waterPhoto }}" alt="" class="absolute inset-0 -z-20 size-full object-cover" />
            @else
                <div class="absolute inset-0 -z-20 bg-linear-to-b from-brand-100 via-brand-200 to-brand-400 dark:from-brand-600 dark:via-brand-800 dark:to-brand-950">
                    <div class="absolute -top-40 left-1/3 h-[36rem] w-[64rem] -translate-x-1/2 rounded-full bg-radial from-white/70 via-brand-100/30 to-transparent blur-2xl dark:from-brand-200/45 dark:via-brand-400/15"></div>
                    <div class="auth-rays absolute inset-x-0 top-0 h-2/3 opacity-40"></div>
                    <div class="absolute inset-0" aria-hidden="true">
                        @foreach ([[6, 10, 0], [17, 6, 3], [29, 14, 6], [41, 8, 1.5], [55, 12, 4.5], [66, 6, 2], [78, 10, 7], [91, 8, 5]] as [$left, $size, $delay])
                            <span class="auth-bubble absolute bottom-0 rounded-full border border-brand-700/25 bg-white/40 dark:border-white/40 dark:bg-white/10"
                                style="left: {{ $left }}%; width: {{ $size }}px; height: {{ $size }}px; animation-delay: {{ $delay }}s;"></span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Wash towards the left (welcome text) for legibility: light in light mode, dark in dark mode --}}
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-white/85 via-white/55 to-white/70 dark:from-brand-950/80 dark:via-brand-950/45 dark:to-brand-950/60"></div>

            <div class="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-40 overflow-hidden" aria-hidden="true">
                <svg class="auth-wave absolute bottom-0 h-full w-[200%] fill-brand-500/20" viewBox="0 0 2880 160" preserveAspectRatio="none">
                    <path d="M0 80C240 40 480 40 720 80s480 40 720 0 480-40 720 0 480 40 720 0v80H0Z" />
                </svg>
                <svg class="auth-wave auth-wave-slow absolute bottom-0 h-3/4 w-[200%] fill-white/50 dark:fill-brand-950/60" viewBox="0 0 2880 160" preserveAspectRatio="none">
                    <path d="M0 90C240 130 480 130 720 90s480-40 720 0 480 40 720 0 480-40 720 0v70H0Z" />
                </svg>
            </div>

            {{-- ─── Content ─── --}}
            <div class="mx-auto flex min-h-svh max-w-6xl flex-col px-6 py-6 lg:px-10">
                {{-- Brand + theme toggle --}}
                <div class="flex items-center justify-between">
                <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center gap-2.5" aria-label="{{ config('app.name') }} home">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-brand-600 ring-1 ring-brand-700/20 dark:bg-white/15 dark:ring-white/25 dark:backdrop-blur">
                        <svg viewBox="0 0 24 24" class="size-5 fill-white" aria-hidden="true">
                            <path d="M12 2.5c3.3 4.1 6 7.4 6 11a6 6 0 1 1-12 0c0-3.6 2.7-6.9 6-11Z" />
                        </svg>
                    </span>
                    <span class="text-xl font-semibold tracking-tight text-brand-950 dark:text-white">
                        {{ str(config('app.name'))->before(' ') }}<span class="text-brand-600 dark:text-brand-300"> {{ str(config('app.name'))->after(' ') }}</span>
                    </span>
                </a>

                <button type="button" x-data x-on:click="$flux.dark = ! $flux.dark" aria-label="{{ __('Toggle dark mode') }}"
                    class="flex size-10 items-center justify-center rounded-xl text-brand-900 ring-1 ring-brand-900/15 transition hover:bg-white/60 dark:text-white dark:ring-white/20 dark:hover:bg-white/10">
                    <flux:icon.moon variant="mini" class="size-5 dark:hidden" />
                    <flux:icon.sun variant="mini" class="hidden size-5 dark:block" />
                </button>
                </div>

                <div class="grid flex-1 items-center gap-12 py-8 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:py-6">
                    {{-- Welcome --}}
                    <div class="hidden text-brand-950 lg:block dark:text-white">
                        <h1 class="text-5xl font-extrabold leading-[1.05] tracking-tight text-balance xl:text-6xl">{{ $heroTitle }}</h1>
                        <p class="mt-6 max-w-md text-base leading-7 text-brand-900/80 dark:text-brand-50/85">{{ $heroText }}</p>

                        <div class="mt-8 flex gap-4">
                            <a href="{{ $social['facebook'] ?? '#' }}" aria-label="Facebook" class="text-brand-800/80 transition hover:text-brand-600 dark:text-white/85 dark:hover:text-brand-300">
                                <svg viewBox="0 0 24 24" class="size-6 fill-current" aria-hidden="true"><path d="M13.5 21v-7.5h2.5l.5-3h-3V8.6c0-.9.3-1.6 1.6-1.6h1.5V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.4H8v3h2.5V21h3Z" /></svg>
                            </a>
                            <a href="{{ $social['x'] ?? '#' }}" aria-label="X" class="text-brand-800/80 transition hover:text-brand-600 dark:text-white/85 dark:hover:text-brand-300">
                                <svg viewBox="0 0 24 24" class="size-6 fill-current" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.3l-4.9-6.4L5.2 21H2.1l7.3-8.3L1.8 3h6.4l4.4 5.9L17.8 3Zm-1.1 16.2h1.7L7.3 4.7H5.5l11.2 14.5Z" /></svg>
                            </a>
                            <a href="{{ $social['linkedin'] ?? '#' }}" aria-label="LinkedIn" class="text-brand-800/80 transition hover:text-brand-600 dark:text-white/85 dark:hover:text-brand-300">
                                <svg viewBox="0 0 24 24" class="size-6 fill-current" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9.75h4V21H3V9.75Zm6.5 0h3.8v1.55h.06c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.77 2.65 4.77 6.1V21h-4v-5.1c0-1.22-.02-2.78-1.7-2.78-1.7 0-1.95 1.32-1.95 2.69V21h-4V9.75Z" /></svg>
                            </a>
                        </div>
                    </div>

                    {{-- Form: follows the light / dark theme --}}
                    <div class="auth-card mx-auto w-full {{ request()->routeIs('access.request') ? 'max-w-lg' : 'max-w-sm' }} text-brand-950 lg:mx-0 lg:justify-self-end dark:text-white">
                        <div class="lg:hidden mb-6 text-center">
                            <h1 class="text-3xl font-extrabold tracking-tight">{{ $heroTitle }}</h1>
                        </div>

                        {{ $slot }}

                        <p class="mt-5 text-center text-xs text-brand-900/70 dark:text-brand-50/75">
                            {{ $consent }}<br>
                            <a href="{{ route('marketing.terms') }}" wire:navigate class="underline underline-offset-4 hover:text-brand-600 dark:hover:text-white">Terms of Service</a>
                            <span class="mx-1">|</span>
                            <a href="{{ route('marketing.privacy') }}" wire:navigate class="underline underline-offset-4 hover:text-brand-600 dark:hover:text-white">Privacy Policy</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
