{{-- The mark inside the brand chip: vendor icon (or logo), vendor initials, or the platform HumSols mark. --}}
@if ($brand['icon'])
    <img src="{{ $brand['icon'] }}" alt="" class="size-full object-cover" />
@elseif ($brand['logo'])
    <img src="{{ $brand['logo'] }}" alt="" class="size-full bg-white object-contain p-0.5" />
@elseif ($brand['platform'])
    <img src="{{ asset('images/brand/humsols-mark.png') }}" alt="" class="size-full bg-white object-contain p-0.5" />
@else
    <span class="text-xs font-bold tracking-wide">{{ $brand['initials'] }}</span>
@endif
