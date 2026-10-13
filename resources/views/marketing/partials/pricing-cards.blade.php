{{--
    The three plan cards. When `toggle` is true the cards read the Alpine
    `annual` flag from a parent x-data to switch monthly / annual prices.
--}}
@php $toggle = $toggle ?? false; @endphp

<div class="mx-auto grid max-w-md gap-6 lg:max-w-none lg:grid-cols-3 lg:items-stretch">
    @foreach (config('marketing.plans') as $plan)
        @php $featured = $plan['featured']; @endphp
        <div class="relative flex flex-col rounded-2xl p-7 sm:p-8 {{ $featured
            ? 'bg-linear-to-br from-navy via-navy-mid to-teal text-white shadow-aqua-lg ring-2 ring-aqua lg:-my-4 lg:py-12'
            : 'border border-teal/10 bg-white shadow-aqua dark:border-white/10 dark:bg-navy' }}">
            @if ($featured)
                <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-aqua px-3.5 py-1 text-xs font-semibold text-navy shadow-aqua">Most popular</span>
            @endif

            <h3 class="text-lg font-semibold {{ $featured ? 'text-white' : 'text-navy dark:text-white' }}">{{ $plan['name'] }}</h3>
            <p class="mt-1 text-sm {{ $featured ? 'text-zinc-200' : 'text-zinc-500 dark:text-zinc-400' }}">{{ $plan['blurb'] }}</p>

            <div class="mt-6 min-h-20">
                @if ($plan['monthly'])
                    <p class="flex items-baseline gap-2">
                        <span class="text-sm font-semibold {{ $featured ? 'text-light-aqua' : 'text-teal dark:text-light-aqua' }}">KES</span>
                        @if ($toggle)
                            <span x-show="! annual" class="font-mono text-4xl font-semibold tracking-tight {{ $featured ? 'text-white' : 'text-navy dark:text-white' }}">{{ number_format($plan['monthly']) }}</span>
                            <span x-show="annual" x-cloak class="font-mono text-4xl font-semibold tracking-tight {{ $featured ? 'text-white' : 'text-navy dark:text-white' }}">{{ number_format($plan['annual']) }}</span>
                        @else
                            <span class="font-mono text-4xl font-semibold tracking-tight {{ $featured ? 'text-white' : 'text-navy dark:text-white' }}">{{ number_format($plan['monthly']) }}</span>
                        @endif
                    </p>
                    <p class="mt-1 text-sm {{ $featured ? 'text-zinc-200' : 'text-zinc-500 dark:text-zinc-400' }}">
                        @if ($toggle)
                            <span x-show="! annual">per month</span>
                            <span x-show="annual" x-cloak>per year · 2 months free</span>
                        @else
                            per month
                        @endif
                    </p>
                @else
                    <p class="text-4xl font-semibold tracking-tight text-navy dark:text-white">Custom</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">priced to your network</p>
                @endif
            </div>

            <p class="mt-2 inline-flex items-center gap-2 text-sm font-semibold {{ $featured ? 'text-light-aqua' : 'text-teal dark:text-light-aqua' }}">
                <flux:icon.beaker variant="mini" class="size-4" /> {{ $plan['limit'] }}
            </p>

            <ul class="mt-6 flex-1 space-y-3 text-sm">
                @foreach ($plan['features'] as $feature)
                    <li class="flex gap-3">
                        <flux:icon.check-circle variant="mini" class="mt-0.5 size-4 shrink-0 {{ $featured ? 'text-light-aqua' : 'text-aqua' }}" />
                        <span class="{{ $featured ? 'text-zinc-100' : 'text-zinc-600 dark:text-zinc-300' }}">{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>

            <a href="{{ $plan['monthly'] ? route('access.request') : route('marketing.contact') }}"
                class="btn-mk mt-8 w-full {{ $featured ? 'btn-mk-primary' : 'btn-mk-secondary' }}">
                {{ $plan['cta'] }}
            </a>
        </div>
    @endforeach
</div>
