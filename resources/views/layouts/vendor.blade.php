<x-layouts::app :title="$title ?? __('Vendor')">
    @if (session('status'))
        <div class="px-4 lg:px-6 pt-4">
            <flux:callout variant="success" icon="check-circle">
                {{ session('status') }}
            </flux:callout>
        </div>
    @endif

    @if (session('error'))
        <div class="px-4 lg:px-6 pt-4">
            <flux:callout variant="danger" icon="exclamation-triangle">
                {{ session('error') }}
            </flux:callout>
        </div>
    @endif

    @yield('content')
    {{ $slot ?? '' }}
</x-layouts::app>