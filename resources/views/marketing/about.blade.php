@extends('layouts.marketing')

@section('title', 'About')
@section('meta_description', 'Human Solutions builds billing software for the small water companies, borehole operators and estate managers who keep neighbourhoods supplied.')

@php
    $values = [
        ['scale', 'Accurate to the cent', 'A bill is a promise. We never round in the dark, and every figure on an invoice can be traced back to a reading and a tariff.'],
        ['device-phone-mobile', 'Built for the field', 'Meters live at gates, in basements and behind hedges. Everything works on an ordinary phone, in ordinary signal.'],
        ['eye', 'Clear for the customer', 'People pay bills they understand. Every invoice shows the reading, the usage and how the total was reached.'],
        ['lock-closed', 'Your data is yours', 'Each vendor has a separate workspace. You can export your records whenever you like, including when you leave.'],
    ];
@endphp

@section('content')
    @include('marketing.partials.page-hero', [
        'eyebrow' => 'About us',
        'title' => 'Software for the people who keep the taps running.',
        'lead' => 'Small water vendors supply millions of homes. They deserve billing tools as good as the big utilities have.',
    ])

    {{-- Mission --}}
    @component('marketing.partials.section', ['class' => 'bg-white dark:bg-navy-deep'])
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-teal dark:text-light-aqua">Our mission</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-navy sm:text-4xl dark:text-white">
                    Make fair, accurate water billing easy for every vendor.
                </h2>
                <p class="mt-5 text-lg text-pretty text-zinc-600 dark:text-zinc-300">
                    When billing is slow or wrong, everyone loses. Customers stop trusting their bills, payments arrive late, and the vendor cannot afford the next pump repair. Getting the bill right, and getting it out on time, is what keeps water flowing.
                </p>
            </div>

            <div class="relative">
                <div class="absolute inset-4 rounded-[2.5rem] bg-linear-to-br from-aqua/25 to-teal/15 blur-2xl" aria-hidden="true"></div>
                <div class="relative grid grid-cols-2 gap-4">
                    @foreach ([['beaker', 'Reading'], ['document-text', 'Invoice'], ['banknotes', 'Payment'], ['chart-bar', 'Report']] as [$icon, $label])
                        <div class="mk-card flex flex-col items-center gap-3 p-8 {{ $loop->even ? 'translate-y-6' : '' }}">
                            <span class="flex size-14 items-center justify-center rounded-2xl bg-linear-to-br from-aqua to-teal text-white shadow-aqua">
                                <flux:icon :name="$icon" class="size-7" />
                            </span>
                            <span class="font-semibold text-navy dark:text-white">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endcomponent

    @include('marketing.partials.wave-divider', ['from' => 'bg-white dark:bg-navy-deep', 'to' => 'text-aqua-tint dark:text-navy'])

    {{-- Story --}}
    @component('marketing.partials.section', ['class' => 'bg-aqua-tint dark:bg-navy', 'width' => 'max-w-3xl'])
        <p class="text-sm font-semibold uppercase tracking-widest text-teal dark:text-light-aqua">Our story</p>
        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-navy sm:text-4xl dark:text-white">It started with an exercise book.</h2>
        <div class="mt-6 space-y-5 text-lg leading-8 text-pretty text-zinc-600 dark:text-zinc-300">
            <p>
                Most small water vendors bill the same way: a reader walks the route with a notebook, someone types the numbers into a spreadsheet, and invoices are worked out one row at a time. It works, until there are two hundred meters, three tariff bands and a customer disputing last March.
            </p>
            <p>
                HS Water Billing began as a way to take that monthly week of typing away. Readings go in at the meter. Tariffs are applied the same way for everyone. Invoices, payments and balances stay in step without anyone reconciling them by hand.
            </p>
            <p>
                Since then it has grown into the system a vendor runs the whole business on: expenses, repairs, assets and a full set of accounts, all fed by the same readings.
            </p>
        </div>
    @endcomponent

    @include('marketing.partials.wave-divider', ['from' => 'bg-aqua-tint dark:bg-navy', 'to' => 'text-white dark:text-navy-deep', 'flip' => true])

    {{-- Values --}}
    @component('marketing.partials.section', ['class' => 'bg-white dark:bg-navy-deep'])
        @include('marketing.partials.heading', [
            'eyebrow' => 'What we value',
            'title' => 'Four things we will not compromise on.',
        ])

        <div class="mt-14 grid gap-6 sm:grid-cols-2">
            @foreach ($values as [$icon, $title, $text])
                <div class="mk-card flex gap-5 p-7">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-aqua-mist text-teal dark:bg-aqua/15 dark:text-light-aqua">
                        <flux:icon :name="$icon" class="size-6" />
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-navy dark:text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-pretty text-zinc-600 dark:text-zinc-300">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endcomponent

    {{-- Team --}}
    @component('marketing.partials.section', ['class' => 'bg-navy dark:bg-navy', 'padding' => 'py-20 lg:py-24'])
        @include('marketing.partials.heading', [
            'tone' => 'light',
            'eyebrow' => 'The team',
            'title' => 'Small, close to our customers, and often in the field.',
        ])

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (config('marketing.team') as $member)
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
                    <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-linear-to-br from-aqua to-teal text-white">
                        <flux:icon :name="$member['icon']" class="size-7" />
                    </span>
                    <h3 class="mt-4 font-semibold text-white">{{ $member['role'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-pretty text-zinc-300">{{ $member['focus'] }}</p>
                </div>
            @endforeach
        </div>
    @endcomponent

    @include('marketing.partials.cta-band', [
        'title' => 'Come and see what we have built.',
        'lead' => 'Request access for your business, or book a demo and bring your hardest billing question.',
    ])
@endsection
