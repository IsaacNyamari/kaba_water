@props(['type'])

@php $t = $type instanceof \App\Enums\AccountType ? $type : \App\Enums\AccountType::from($type); @endphp

<flux:badge size="sm" :color="$t->color()">{{ $t->label() }}</flux:badge>
