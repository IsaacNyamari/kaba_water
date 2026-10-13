{{--
    Wave between two sections.
    `from` = background class of the section above.
    `to`   = text-colour class matching the background of the section below
             (the wave is filled with it).
    `flip` mirrors the wave so consecutive dividers don't repeat.
--}}
<div class="{{ $from }} -mb-px overflow-hidden leading-none" aria-hidden="true">
    <svg viewBox="0 0 1440 80" preserveAspectRatio="none"
        class="block h-10 w-full fill-current sm:h-14 lg:h-20 {{ $to }} {{ ($flip ?? false) ? '-scale-x-100' : '' }}">
        <path class="opacity-40" d="M0 30C160 58 300 64 460 46S760 4 940 14s320 44 500 26v40H0Z" />
        <path d="M0 44C120 16 240 2 360 2s240 26 360 40 240 14 360 0S1320 2 1440 2v78H0Z" />
    </svg>
</div>
