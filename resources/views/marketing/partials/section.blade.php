{{-- Padding + max-width wrapper. Use with @component(...) ... @endcomponent. --}}
<section @isset($id) id="{{ $id }}" @endisset class="scroll-mt-16 {{ $class ?? '' }}">
    <div class="mx-auto px-5 sm:px-8 {{ $width ?? 'max-w-7xl' }} {{ $padding ?? 'py-20 lg:py-28' }}">
        {{ $slot }}
    </div>
</section>
