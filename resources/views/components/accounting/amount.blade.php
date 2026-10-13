{{-- Right-aligned money: font-mono, negatives in rose (CLAUDE.md UI rules). --}}
@props(['value', 'strong' => false, 'blankZero' => false])

@php
    $v = (string) ($value ?? '0');
    $isZero = \App\Support\Money\MoneyMath::isZero($v);
    $neg = \App\Support\Money\MoneyMath::isNegative($v);
@endphp

<span {{ $attributes->merge(['class' => 'font-mono tabular-nums ' . ($neg ? 'text-rose-600' : '') . ($strong ? ' font-semibold' : '')]) }}>
    {{ $blankZero && $isZero ? '—' : \App\Support\Money\MoneyMath::format($v) }}
</span>
