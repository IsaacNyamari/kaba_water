{{-- HumSols logo (transparent). Pass `tone` = 'light' on dark backgrounds; otherwise it follows light/dark mode. --}}
@php $light = ($tone ?? 'auto') === 'light'; @endphp

<a href="{{ route('home') }}" wire:navigate class="group inline-flex items-center" aria-label="{{ config('app.name') }} home">
    @if ($light)
        <img src="{{ asset('images/brand/humsols-logo-dark.png') }}" alt="HumSols — Human Solutions" class="h-10 w-auto transition group-hover:opacity-90" />
    @else
        <img src="{{ asset('images/brand/humsols-logo.png') }}" alt="HumSols — Human Solutions" class="h-10 w-auto transition group-hover:opacity-90 dark:hidden" />
        <img src="{{ asset('images/brand/humsols-logo-dark.png') }}" alt="HumSols — Human Solutions" class="hidden h-10 w-auto transition group-hover:opacity-90 dark:block" />
    @endif
</a>
