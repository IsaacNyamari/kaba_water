@props([
    'data' => [],
    'height' => '16rem',
    'legend' => false,
    'legendPosition' => 'bottom',
])

<div wire:ignore wire:key="{{ 'chart-' . Str::random(8) }}" x-data="chart(@js($data), @js(['showLegend' => $legend, 'legendPosition' => $legendPosition]))" {{ $attributes }}>
    <canvas x-ref="canvas" style="height: {{ $height }}; width: 100%;"></canvas>
</div>
