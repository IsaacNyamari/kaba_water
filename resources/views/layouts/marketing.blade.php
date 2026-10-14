<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('marketing.partials.meta')

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        @include('partials.theme-sync')
        @livewireStyles
    </head>
    <body class="min-h-screen bg-white font-sans text-zinc-600 antialiased selection:bg-aqua/30 dark:bg-navy-deep dark:text-zinc-300">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-xl focus:bg-aqua focus:px-4 focus:py-2 focus:font-semibold focus:text-navy">
            Skip to content
        </a>

        @include('marketing.partials.nav')

        <main id="main">
            @yield('content')
        </main>

        @include('marketing.partials.footer')

        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>

        @livewireScripts
        @fluxScripts
    </body>
</html>
