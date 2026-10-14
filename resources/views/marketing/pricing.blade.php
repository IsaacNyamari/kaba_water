@extends('layouts.marketing')

@section('title', 'Pricing')
@section('meta_description', 'Simple pricing in Kenya shillings, by the number of meters you manage. Starter, Growth and Enterprise plans with no setup fee.')

@php $planNames = collect(config('marketing.plans'))->pluck('name'); @endphp

@section('content')
    @include('marketing.partials.page-hero', [
        'eyebrow' => 'Pricing',
        'title' => 'Pay for the meters you manage.',
        'lead' => 'Prices in Kenya shillings, no setup fee, and you can change plan whenever your network grows.',
    ])

    {{-- Plans with monthly / annual toggle --}}
    <div x-data="{ annual: false }">
        @component('marketing.partials.section', ['class' => 'bg-aqua-tint dark:bg-navy-deep'])
            <div class="flex justify-center">
                <div class="inline-flex items-center gap-1 rounded-2xl border border-teal/15 bg-white p-1 shadow-aqua dark:border-white/10 dark:bg-navy" role="group" aria-label="Billing period">
                    <button type="button" x-on:click="annual = false" x-bind:aria-pressed="(! annual).toString()"
                        x-bind:class="annual ? 'text-zinc-600 dark:text-zinc-300' : 'bg-navy text-white dark:bg-aqua dark:text-navy'"
                        class="rounded-xl px-5 py-2.5 text-sm font-semibold transition">
                        Monthly
                    </button>
                    <button type="button" x-on:click="annual = true" x-bind:aria-pressed="annual.toString()"
                        x-bind:class="annual ? 'bg-navy text-white dark:bg-aqua dark:text-navy' : 'text-zinc-600 dark:text-zinc-300'"
                        class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition">
                        Annual
                        <span class="rounded-full bg-aqua px-2 py-0.5 text-xs font-semibold text-navy">2 months free</span>
                    </button>
                </div>
            </div>

            <div class="mt-16">
                @include('marketing.partials.pricing-cards', ['toggle' => true])
            </div>

            <p class="mt-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                Prices exclude VAT. Every plan starts with a free trial workspace once your access request is approved.
            </p>
        @endcomponent
    </div>

    @include('marketing.partials.wave-divider', ['from' => 'bg-aqua-tint dark:bg-navy-deep', 'to' => 'text-white dark:text-navy'])

    {{-- Comparison table --}}
    @component('marketing.partials.section', ['id' => 'compare', 'class' => 'bg-white dark:bg-navy'])
        @include('marketing.partials.heading', [
            'eyebrow' => 'Compare plans',
            'title' => 'Every feature, plan by plan.',
        ])

        <div class="mt-12 overflow-x-auto rounded-2xl border border-teal/10 shadow-aqua dark:border-white/10">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="bg-navy text-white dark:bg-navy-deep">
                    <tr>
                        <th scope="col" class="px-5 py-4 font-semibold">Feature</th>
                        @foreach ($planNames as $name)
                            <th scope="col" class="px-5 py-4 text-center font-semibold {{ $loop->iteration === 2 ? 'text-light-aqua' : '' }}">{{ $name }}</th>
                        @endforeach
                    </tr>
                </thead>
                @foreach (config('marketing.comparison') as $group => $rows)
                    <tbody class="divide-y divide-teal/10 dark:divide-white/10">
                        <tr class="bg-aqua-tint dark:bg-white/5">
                            <th scope="colgroup" colspan="4" class="px-5 py-3 text-xs font-semibold uppercase tracking-widest text-teal dark:text-light-aqua">{{ $group }}</th>
                        </tr>
                        @foreach ($rows as $feature => $values)
                            <tr class="bg-white dark:bg-navy">
                                <th scope="row" class="px-5 py-3.5 font-medium text-navy dark:text-zinc-100">{{ $feature }}</th>
                                @foreach ($values as $value)
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($value === true)
                                            <flux:icon.check-circle variant="mini" class="mx-auto size-5 text-aqua" />
                                            <span class="sr-only">Included</span>
                                        @elseif ($value === false)
                                            <flux:icon.minus variant="mini" class="mx-auto size-5 text-zinc-300 dark:text-zinc-600" />
                                            <span class="sr-only">Not included</span>
                                        @else
                                            <span class="font-mono font-semibold text-navy dark:text-white">{{ $value }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    @endcomponent

    {{-- FAQ --}}
    @component('marketing.partials.section', ['id' => 'faq', 'class' => 'bg-aqua-tint dark:bg-navy-deep', 'width' => 'max-w-3xl'])
        @include('marketing.partials.heading', [
            'eyebrow' => 'Questions',
            'title' => 'Asked before signing up.',
        ])

        <div class="mt-12 space-y-3">
            @foreach (config('marketing.faqs') as $faq)
                <details class="mk-card group px-6 py-1 open:shadow-aqua-lg" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 font-semibold text-navy marker:hidden dark:text-white [&::-webkit-details-marker]:hidden">
                        {{ $faq['q'] }}
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-aqua-mist text-teal transition group-open:rotate-45 group-open:bg-aqua group-open:text-navy dark:bg-aqua/15 dark:text-light-aqua">
                            <flux:icon.plus variant="mini" class="size-5" />
                        </span>
                    </summary>
                    <p class="pb-5 pr-10 leading-7 text-pretty text-zinc-600 dark:text-zinc-300">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>

        <p class="mt-10 text-center text-sm text-zinc-600 dark:text-zinc-300">
            Something else?
            <a href="{{ route('marketing.contact') }}" wire:navigate class="font-semibold text-teal underline underline-offset-4 dark:text-light-aqua">Ask us directly</a>
        </p>
    @endcomponent

    @include('marketing.partials.cta-band')
@endsection
