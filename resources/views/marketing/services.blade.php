@extends('layouts.marketing')

@section('title', 'Services & features')
@section('meta_description', 'Meter readings, photo capture, tiered tariffs, invoicing, payments, a client portal and full financial reports. See everything Kaba Water does for a water vendor.')

@php
    $sections = [
        [
            'id' => 'meter-readings',
            'icon' => 'beaker',
            'eyebrow' => 'Meter readings',
            'title' => 'Every reading in one place, with its history.',
            'text' => 'Your reader sees the last reading before entering the new one, so a slipped digit is caught at the gate, not on the bill. Consumption is worked out for you and each reading waits for approval before it can be billed.',
            'points' => ['Previous reading and consumption shown side by side', 'Readings wait for approval before billing', 'Full history per meter and per client'],
            'mockup' => 'reading',
        ],
        [
            'id' => 'ocr',
            'icon' => 'camera',
            'eyebrow' => 'Photo reading capture',
            'title' => 'Photograph the dial. Skip the typing.',
            'text' => 'Take a photo of the meter with any phone and the reading is read straight off the image. Your reader checks the number and confirms it, which is faster than typing and far harder to get wrong.',
            'points' => ['Works with the camera on any smartphone', 'The reader always confirms before saving', 'No extra hardware to buy'],
            'mockup' => 'scan',
        ],
        [
            'id' => 'invoicing',
            'icon' => 'document-text',
            'eyebrow' => 'Invoicing & tariffs',
            'title' => 'Tiered tariffs applied correctly, every time.',
            'text' => 'Set your consumption bands, standing charge and tax once. Kaba Water applies them to each reading and produces a clear invoice that shows the customer exactly how the total was reached.',
            'points' => ['Consumption bands with a different rate for each', 'Draft, sent, paid, overdue and void statuses', 'A branded PDF for every invoice'],
            'mockup' => 'invoice',
        ],
        [
            'id' => 'payments',
            'icon' => 'banknotes',
            'eyebrow' => 'Payments & M-Pesa',
            'title' => 'Know who has paid before you ask.',
            'text' => 'Record an M-Pesa code, a bank transfer or cash against an invoice in seconds. Part payments reduce the balance, full payments close the invoice, and overdue invoices flag themselves.',
            'points' => ['M-Pesa, cash and bank payments with references', 'Part payments and running balances', 'Bad debts written off properly, not deleted'],
            'mockup' => 'payments',
        ],
        [
            'id' => 'client-portal',
            'icon' => 'user-group',
            'eyebrow' => 'Client portal',
            'title' => 'Fewer phone calls, happier customers.',
            'text' => 'Each customer gets their own sign-in. They can check what they owe, download past invoices, see their usage and follow a repair request, at any hour, without ringing your office.',
            'points' => ['Bills, payments and usage in one view', 'Invoices to download as PDF', 'Works on any phone browser'],
            'mockup' => 'portal',
        ],
        [
            'id' => 'reports',
            'icon' => 'chart-bar',
            'eyebrow' => 'Financial reports',
            'title' => 'Books your accountant will recognise.',
            'text' => 'Every invoice, payment and expense is posted to a double-entry ledger as it happens. That gives you an income statement, balance sheet, cash flow and trial balance that always agree, ready to export to Excel.',
            'points' => ['Income statement, balance sheet and cash flow', 'Fiscal periods you can close and lock', 'An accountant role with read-only operations'],
            'mockup' => 'reports',
        ],
    ];

    $extras = [
        ['receipt-percent', 'Expenses & suppliers', 'Log water purchases, salaries and repairs against the supplier, with the category that feeds your reports.'],
        ['wrench-screwdriver', 'Work orders', 'Track leaks, new connections and meter swaps from the request to the finished job.'],
        ['cube', 'Assets & depreciation', 'Keep a register of pumps, tanks and meters and post depreciation month by month.'],
        ['shield-check', 'Roles & permissions', 'Owners, staff, accountants and customers each see only what they need.'],
    ];
@endphp

@section('content')
    @include('marketing.partials.page-hero', [
        'eyebrow' => 'Services',
        'title' => 'One system for the whole billing cycle.',
        'lead' => 'From the reader at the gate to the accountant at year end, Kaba Water keeps everyone working from the same numbers.',
    ])

    {{-- Anchor chips --}}
    <div class="border-b border-teal/10 bg-white dark:border-white/10 dark:bg-navy-deep">
        <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-5 py-4 sm:px-8 lg:justify-center">
            @foreach ($sections as $s)
                <a href="#{{ $s['id'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-full border border-teal/15 px-4 py-2 text-sm font-medium text-navy transition hover:border-aqua hover:bg-aqua/10 dark:border-white/10 dark:text-zinc-200">
                    <flux:icon :name="$s['icon']" variant="mini" class="size-4 text-teal dark:text-light-aqua" /> {{ $s['eyebrow'] }}
                </a>
            @endforeach
        </div>
    </div>

    @foreach ($sections as $s)
        @php $even = $loop->even; @endphp
        @component('marketing.partials.section', [
            'id' => $s['id'],
            'class' => $even ? 'bg-aqua-tint dark:bg-navy' : 'bg-white dark:bg-navy-deep',
            'padding' => 'py-16 lg:py-24',
        ])
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                <div class="{{ $even ? 'lg:order-2' : '' }}">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-widest text-teal dark:text-light-aqua">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-aqua-mist dark:bg-aqua/15">
                            <flux:icon :name="$s['icon']" variant="mini" class="size-5" />
                        </span>
                        {{ $s['eyebrow'] }}
                    </p>
                    <h2 class="mt-5 text-3xl font-semibold tracking-tight text-balance text-navy sm:text-4xl dark:text-white">{{ $s['title'] }}</h2>
                    <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-300">{{ $s['text'] }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach ($s['points'] as $point)
                            <li class="flex gap-3 text-zinc-700 dark:text-zinc-200">
                                <flux:icon.check-circle variant="mini" class="mt-1 size-5 shrink-0 text-aqua" /> {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="relative {{ $even ? 'lg:order-1' : '' }}">
                    <div class="absolute inset-4 -z-0 rounded-[2.5rem] bg-linear-to-br from-aqua/25 to-teal/15 blur-2xl" aria-hidden="true"></div>
                    <div class="relative mx-auto max-w-md">
                        @include('marketing.partials.mockups.'.$s['mockup'])
                        @if ($s['mockup'] === 'invoice')
                            <div class="relative mx-6 -mt-5 sm:absolute sm:-bottom-12 sm:-right-10 sm:mx-0 sm:mt-0 sm:w-64">
                                @include('marketing.partials.mockups.tariff')
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endcomponent
    @endforeach

    @include('marketing.partials.wave-divider', ['from' => 'bg-aqua-tint dark:bg-navy', 'to' => 'text-white dark:text-navy-deep'])

    {{-- And the rest --}}
    @component('marketing.partials.section', ['class' => 'bg-white dark:bg-navy-deep'])
        @include('marketing.partials.heading', [
            'eyebrow' => 'Also included',
            'title' => 'The rest of running a water business.',
            'lead' => 'Billing is the centre, but it is not the whole job.',
        ])

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($extras as [$icon, $title, $text])
                <div class="mk-card p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-aqua-mist text-teal dark:bg-aqua/15 dark:text-light-aqua">
                        <flux:icon :name="$icon" class="size-5" />
                    </span>
                    <h3 class="mt-4 font-semibold text-navy dark:text-white">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-pretty text-zinc-600 dark:text-zinc-300">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    @endcomponent

    @include('marketing.partials.cta-band', [
        'title' => 'See it with your own tariffs.',
        'lead' => 'Book a short demo and we will set up a sample billing run using your bands and charges.',
    ])
@endsection
