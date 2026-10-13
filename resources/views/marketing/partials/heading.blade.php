{{-- Section heading: eyebrow, title, optional lead. `tone` = 'light' on dark backgrounds. --}}
@php
    $light = ($tone ?? null) === 'light';
    $centered = ($align ?? 'center') === 'center';
@endphp

<div class="{{ $centered ? 'mx-auto max-w-2xl text-center' : 'max-w-xl' }}">
    @isset($eyebrow)
        <p class="text-sm font-semibold uppercase tracking-widest {{ $light ? 'text-light-aqua' : 'text-teal dark:text-light-aqua' }}">{{ $eyebrow }}</p>
    @endisset
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl {{ $light ? 'text-white' : 'text-navy dark:text-white' }}">{{ $title }}</h2>
    @isset($lead)
        <p class="mt-4 text-lg text-pretty {{ $light ? 'text-zinc-300' : 'text-zinc-600 dark:text-zinc-300' }}">{{ $lead }}</p>
    @endisset
</div>
