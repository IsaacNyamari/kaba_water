{{-- Water-drop mark + wordmark. Pass `tone` = 'light' on dark backgrounds. --}}
@php $light = ($tone ?? 'auto') === 'light'; @endphp

<a href="{{ route('home') }}" wire:navigate class="group inline-flex items-center gap-2.5" aria-label="{{ config('app.name') }} home">
    <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-aqua to-teal shadow-aqua transition group-hover:scale-105">
        <svg viewBox="0 0 24 24" class="size-5 fill-white" aria-hidden="true">
            <path d="M12 2.5c3.3 4.1 6 7.4 6 11a6 6 0 1 1-12 0c0-3.6 2.7-6.9 6-11Z" />
        </svg>
    </span>
    <span class="text-lg font-semibold tracking-tight {{ $light ? 'text-white' : 'text-navy dark:text-white' }}">
        Kaba<span class="text-aqua"> Water</span>
    </span>
</a>
