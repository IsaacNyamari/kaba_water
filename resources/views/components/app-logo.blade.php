@props([
    'sidebar' => false,
])

@php
    // Inside a workspace the shell carries the vendor's own brand, not the platform's.
    $brand = \App\Support\Workspace::current();
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$brand['name']" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-linear-to-br from-brand-500 to-brand-700 text-white shadow-sm">
            @include('components.partials.workspace-mark', ['brand' => $brand])
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$brand['name']" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-linear-to-br from-brand-500 to-brand-700 text-white shadow-sm">
            @include('components.partials.workspace-mark', ['brand' => $brand])
        </x-slot>
    </flux:brand>
@endif
