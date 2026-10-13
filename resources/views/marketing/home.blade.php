@extends('layouts.marketing')

@section('title', 'Water billing software for vendors')
@section('meta_description', 'Kaba Water turns meter readings into invoices and payments. Built for small water companies, borehole operators and estate managers in Kenya.')

@php
    $features = [
        ['beaker', 'Meter readings', 'Record readings per meter with the previous reading, consumption and history on one screen.', 'meter-readings'],
        ['camera', 'Photo reading capture', 'Point a phone at the dial. The reading is read off the photo and you confirm it before it is saved.', 'ocr'],
        ['document-text', 'Invoicing & tariffs', 'Tiered tariffs, standing charges and tax are applied for you. Every bill is a clean PDF.', 'invoicing'],
        ['banknotes', 'Payments & M-Pesa', 'Record M-Pesa, cash and bank payments against an invoice and the balance updates at once.', 'payments'],
        ['user-group', 'Client portal', 'Customers sign in to see their bills, usage and payment history without calling your office.', 'client-portal'],
        ['chart-bar', 'Financial reports', 'Income statement, balance sheet and cash flow from a proper double-entry ledger.', 'reports'],
    ];

    $steps = [
        ['Add clients & meters', 'Create each customer once with their meter and tariff. Opening balances bring across what they already owe.', 'user-plus'],
        ['Capture readings', 'Your reader walks the route and enters or photographs each dial. Unusual consumption stands out before billing.', 'camera'],
        ['Generate invoices', 'One click turns approved readings into invoices. Send them, record payments and watch the balance fall.', 'document-check'],
    ];
@endphp

@section('content')
    {{-- ═══════════ Hero ═══════════ --}}
    <section class="relative isolate overflow-hidden bg-linear-to-b from-aqua-tint to-white dark:from-navy dark:to-navy-deep">
        <div class="absolute -top-32 left-1/2 -z-10 size-[46rem] -translate-x-1/2 rounded-full bg-radial from-aqua/30 to-transparent to-70% blur-2xl lg:left-1/4" aria-hidden="true"></div>
        <div class="absolute -right-40 top-40 -z-10 size-[30rem] rounded-full bg-radial from-light-aqua/30 to-transparent to-70% blur-2xl" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 pb-20 pt-16 sm:px-8 lg:grid-cols-[1.05fr_1fr] lg:gap-10 lg:pb-28 lg:pt-24">
            <div class="text-center lg:text-left">
                <p class="inline-flex items-center gap-2 rounded-full border border-teal/15 bg-white/70 px-3.5 py-1.5 text-xs font-semibold text-teal shadow-aqua backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-light-aqua">
                    <span class="size-1.5 rounded-full bg-aqua"></span>
                    Billing built for water vendors
                </p>

                <h1 class="mt-6 text-4xl font-semibold tracking-tight text-balance text-navy sm:text-5xl lg:text-6xl dark:text-white">
                    Bill every drop,
                    <span class="bg-linear-to-r from-teal to-aqua bg-clip-text text-transparent dark:from-aqua dark:to-light-aqua">effortlessly.</span>
                </h1>

                <p class="mx-auto mt-6 max-w-xl text-lg text-pretty text-zinc-600 lg:mx-0 dark:text-zinc-300">
                    Kaba Water takes you from the meter reading to the invoice to the payment in one place, so month-end takes an afternoon instead of a week.
                </p>

                <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                    <a href="{{ route('access.request') }}" class="btn-mk btn-mk-primary w-full sm:w-auto">
                        Start free <flux:icon.arrow-right variant="micro" class="size-4" />
                    </a>
                    <a href="{{ route('marketing.contact') }}" class="btn-mk btn-mk-secondary w-full sm:w-auto">Book a demo</a>
                </div>

                <ul class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-zinc-500 lg:justify-start dark:text-zinc-400">
                    @foreach (['Set up in a day', 'Works on any phone', 'Prices in KES'] as $point)
                        <li class="inline-flex items-center gap-1.5">
                            <flux:icon.check-circle variant="mini" class="size-4 text-aqua" /> {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Product mockup --}}
            <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                <div class="absolute inset-6 -z-10 rounded-[2.5rem] bg-linear-to-br from-aqua/30 to-teal/20 blur-2xl" aria-hidden="true"></div>

                <div class="rounded-3xl border border-teal/10 bg-white/70 p-3 shadow-aqua-lg backdrop-blur sm:p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-center gap-1.5 px-2 pb-3" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-rose-300"></span>
                        <span class="size-2.5 rounded-full bg-amber-300"></span>
                        <span class="size-2.5 rounded-full bg-aqua"></span>
                        <span class="ml-3 h-5 flex-1 rounded-md bg-teal/10 dark:bg-white/10"></span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-5">
                        <div class="sm:col-span-3">@include('marketing.partials.mockups.invoice')</div>
                        <div class="grid gap-3 sm:col-span-2">
                            <div class="mk-card p-4" aria-hidden="true">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Collected in October</p>
                                <p class="mt-1 font-mono text-xl font-semibold text-navy dark:text-white">412,600</p>
                                <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-teal dark:text-light-aqua">
                                    <flux:icon.arrow-trending-up variant="micro" class="size-3.5" /> 12% on September
                                </p>
                            </div>
                            <div class="mk-card p-4" aria-hidden="true">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Readings captured</p>
                                <p class="mt-1 font-mono text-xl font-semibold text-navy dark:text-white">386 / 402</p>
                                <div class="mt-2 h-1.5 rounded-full bg-teal/10 dark:bg-white/10">
                                    <div class="h-1.5 w-11/12 rounded-full bg-linear-to-r from-aqua to-teal"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative mx-4 -mt-6 sm:absolute sm:-bottom-10 sm:-left-8 sm:mx-0 sm:mt-0 sm:w-72 lg:-left-12">
                    @include('marketing.partials.mockups.reading')
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════ Trust strip ═══════════ --}}
    @component('marketing.partials.section', ['class' => 'bg-white dark:bg-navy-deep', 'padding' => 'pb-16 pt-10 lg:pt-20'])
        <p class="text-center text-sm font-medium text-zinc-500 dark:text-zinc-400">
            Trusted by <span class="font-semibold text-navy dark:text-white">{{ config('marketing.trust.vendor_count') }} water vendors</span> across Kenya
        </p>
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach (config('marketing.trust.logos') as $logo)
                <div class="flex h-16 items-center justify-center gap-2 rounded-2xl border border-dashed border-teal/20 bg-aqua-tint/60 px-4 text-sm font-semibold text-teal/80 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300">
                    <svg viewBox="0 0 24 24" class="size-4 shrink-0 fill-current" aria-hidden="true"><path d="M12 2.5c3.3 4.1 6 7.4 6 11a6 6 0 1 1-12 0c0-3.6 2.7-6.9 6-11Z" /></svg>
                    <span class="truncate">{{ $logo }}</span>
                </div>
            @endforeach
        </div>
    @endcomponent

    {{-- ═══════════ Features ═══════════ --}}
    @component('marketing.partials.section', ['id' => 'features', 'class' => 'bg-white dark:bg-navy-deep', 'padding' => 'pb-20 pt-6 lg:pb-28'])
        @include('marketing.partials.heading', [
            'eyebrow' => 'What you get',
            'title' => 'Everything between the meter and the bank.',
            'lead' => 'One system for the people who read meters, the people who send bills and the people who keep the books.',
        ])

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as [$icon, $title, $text, $anchor])
                <a href="{{ route('marketing.services') }}#{{ $anchor }}"
                    class="mk-card group p-7 transition duration-200 hover:-translate-y-1 hover:border-aqua/40 hover:shadow-aqua-lg">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-aqua-mist text-teal transition group-hover:bg-aqua group-hover:text-navy dark:bg-aqua/15 dark:text-light-aqua">
                        <flux:icon :name="$icon" class="size-6" />
                    </span>
                    <h3 class="mt-5 text-lg font-semibold text-navy dark:text-white">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-pretty text-zinc-600 dark:text-zinc-300">{{ $text }}</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-teal dark:text-light-aqua">
                        Learn more <flux:icon.arrow-right variant="micro" class="size-4 transition group-hover:translate-x-1" />
                    </span>
                </a>
            @endforeach
        </div>
    @endcomponent

    @include('marketing.partials.wave-divider', ['from' => 'bg-white dark:bg-navy-deep', 'to' => 'text-aqua-tint dark:text-navy'])

    {{-- ═══════════ How it works ═══════════ --}}
    @component('marketing.partials.section', ['id' => 'how-it-works', 'class' => 'bg-aqua-tint dark:bg-navy'])
        @include('marketing.partials.heading', [
            'eyebrow' => 'How it works',
            'title' => 'Three steps from reading to revenue.',
            'lead' => 'No training manual. If your team can use a phone, they can run a billing cycle.',
        ])

        <ol class="relative mt-16 grid gap-10 lg:grid-cols-3 lg:gap-8">
            <li class="absolute left-[16.66%] right-[16.66%] top-7 hidden border-t-2 border-dashed border-aqua/60 lg:block" aria-hidden="true"></li>

            @foreach ($steps as [$title, $text, $icon])
                <li class="relative flex gap-5 lg:flex-col lg:items-center lg:gap-0 lg:text-center">
                    <span class="relative z-10 flex size-14 shrink-0 items-center justify-center rounded-2xl bg-aqua font-mono text-xl font-semibold text-navy shadow-aqua ring-8 ring-aqua-tint dark:ring-navy">
                        {{ $loop->iteration }}
                    </span>
                    <div class="lg:mt-6">
                        <h3 class="flex items-center gap-2 text-lg font-semibold text-navy lg:justify-center dark:text-white">
                            <flux:icon :name="$icon" variant="mini" class="size-5 text-teal dark:text-light-aqua" /> {{ $title }}
                        </h3>
                        <p class="mt-2 max-w-sm text-sm leading-6 text-pretty text-zinc-600 dark:text-zinc-300">{{ $text }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endcomponent

    @include('marketing.partials.wave-divider', ['from' => 'bg-aqua-tint dark:bg-navy', 'to' => 'text-navy dark:text-navy-deep', 'flip' => true])

    {{-- ═══════════ Stats band ═══════════ --}}
    @component('marketing.partials.section', ['class' => 'bg-navy dark:bg-navy-deep', 'padding' => 'py-16 lg:py-20'])
        <dl class="grid grid-cols-2 gap-x-6 gap-y-10 text-center lg:grid-cols-4">
            @foreach (config('marketing.stats') as $stat)
                <div>
                    <dd class="font-mono text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $stat['value'] }}</dd>
                    <dt class="mt-2 text-sm font-medium text-light-aqua">{{ $stat['label'] }}</dt>
                </div>
            @endforeach
        </dl>
    @endcomponent

    @include('marketing.partials.wave-divider', ['from' => 'bg-navy dark:bg-navy-deep', 'to' => 'text-white dark:text-navy'])

    {{-- ═══════════ Testimonial ═══════════ --}}
    @php $testimonial = config('marketing.testimonial'); @endphp
    @component('marketing.partials.section', ['class' => 'bg-white dark:bg-navy', 'width' => 'max-w-4xl'])
        <figure class="mk-card relative p-8 text-center sm:p-12 dark:bg-navy-deep">
            <span class="absolute -top-6 left-1/2 flex size-12 -translate-x-1/2 items-center justify-center rounded-2xl bg-aqua text-navy shadow-aqua">
                <flux:icon.chat-bubble-left-ellipsis variant="solid" class="size-6" />
            </span>
            <blockquote class="text-xl font-medium leading-relaxed text-pretty text-navy sm:text-2xl dark:text-white">
                &ldquo;{{ $testimonial['quote'] }}&rdquo;
            </blockquote>
            <figcaption class="mt-8 flex items-center justify-center gap-4">
                <span class="flex size-12 items-center justify-center rounded-full bg-linear-to-br from-aqua to-teal font-semibold text-white">{{ $testimonial['initials'] }}</span>
                <span class="text-left">
                    <span class="block font-semibold text-navy dark:text-white">{{ $testimonial['name'] }}</span>
                    <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $testimonial['role'] }}</span>
                </span>
            </figcaption>
        </figure>
    @endcomponent

    @include('marketing.partials.wave-divider', ['from' => 'bg-white dark:bg-navy', 'to' => 'text-aqua-tint dark:text-navy-deep', 'flip' => true])

    {{-- ═══════════ Pricing preview ═══════════ --}}
    @component('marketing.partials.section', ['id' => 'pricing', 'class' => 'bg-aqua-tint dark:bg-navy-deep'])
        @include('marketing.partials.heading', [
            'eyebrow' => 'Pricing',
            'title' => 'Priced by the size of your network.',
            'lead' => 'Start small and move up when you add meters. No setup fee on any plan.',
        ])

        <div class="mt-16">
            @include('marketing.partials.pricing-cards')
        </div>

        <p class="mt-12 text-center text-sm text-zinc-600 dark:text-zinc-300">
            Want the full comparison?
            <a href="{{ route('marketing.pricing') }}" class="font-semibold text-teal underline underline-offset-4 dark:text-light-aqua">See every feature by plan</a>
        </p>
    @endcomponent

    @include('marketing.partials.cta-band')
@endsection
