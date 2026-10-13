@props(['url'])
@php
    $name = trim(strip_tags((string) $slot));
    $first = \Illuminate\Support\Str::before($name, ' ');
    $rest = str_contains($name, ' ') ? \Illuminate\Support\Str::after($name, ' ') : '';
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{ $first }}@if ($rest !== '')<span class="brand-accent"> {{ $rest }}</span>@endif
</a>
<p class="brand-tagline">Water billing, made simple</p>
</td>
</tr>
