{{-- Platform mark: the HumSols "two people" mark, in light- and dark-mode colours. --}}
<img src="{{ asset('images/brand/humsols-mark.png') }}" alt="" {{ $attributes->merge(['class' => 'object-contain dark:hidden']) }} />
<img src="{{ asset('images/brand/humsols-mark-dark.png') }}" alt="" {{ $attributes->merge(['class' => 'hidden object-contain dark:block']) }} />
