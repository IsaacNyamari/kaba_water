{{-- The mark inside the brand chip: vendor icon (or logo), vendor initials, or the platform water drop. --}}
@if ($brand['icon'])
    <img src="{{ $brand['icon'] }}" alt="" class="size-full object-cover" />
@elseif ($brand['logo'])
    <img src="{{ $brand['logo'] }}" alt="" class="size-full bg-white object-contain p-0.5" />
@elseif ($brand['platform'])
    <x-app-logo-icon class="size-5 fill-current text-white" />
@else
    <span class="text-xs font-bold tracking-wide">{{ $brand['initials'] }}</span>
@endif
