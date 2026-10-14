<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    @php $brandName = \App\Support\Workspace::current()['name']; @endphp
    {{ filled($title ?? null) ? $title.' - '.$brandName : $brandName }}
</title>

{{-- A workspace icon replaces the default favicons entirely; listing both lets the browser pick the default. --}}
@if ($workspaceIcon = \App\Support\Workspace::current()['icon'])
    <link rel="icon" href="{{ $workspaceIcon }}">
    <link rel="apple-touch-icon" href="{{ $workspaceIcon }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@include('partials.theme-sync')
