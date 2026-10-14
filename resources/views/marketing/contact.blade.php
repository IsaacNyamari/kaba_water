@extends('layouts.marketing')

@section('title', 'Contact')
@section('meta_description', 'Talk to the Kaba Water team. Book a demo, ask about pricing, or get help moving your clients and meters across.')

@php $contact = config('marketing.contact'); @endphp

@section('content')
    @include('marketing.partials.page-hero', [
        'eyebrow' => 'Contact',
        'title' => 'Talk to someone who knows water billing.',
        'lead' => 'Tell us how many meters you manage and how you bill today. We reply within one working day.',
    ])

    @component('marketing.partials.section', ['class' => 'bg-aqua-tint dark:bg-navy-deep'])
        <div class="grid gap-8 lg:grid-cols-[1.5fr_1fr] lg:gap-12">
            {{-- Form --}}
            <livewire:marketing.contact-form />

            {{-- Sidebar --}}
            <aside class="space-y-6">
                <div class="rounded-2xl bg-linear-to-br from-navy via-navy-mid to-teal p-7 text-white shadow-aqua-lg">
                    <h2 class="text-lg font-semibold">Reach us directly</h2>
                    <ul class="mt-6 space-y-5 text-sm">
                        <li class="flex gap-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-light-aqua"><flux:icon.phone variant="mini" class="size-5" /></span>
                            <span>
                                <span class="block text-xs uppercase tracking-widest text-light-aqua">Phone</span>
                                <a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}" class="mt-0.5 block font-semibold hover:text-light-aqua">{{ $contact['phone'] }}</a>
                            </span>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-light-aqua"><flux:icon.envelope variant="mini" class="size-5" /></span>
                            <span class="min-w-0">
                                <span class="block text-xs uppercase tracking-widest text-light-aqua">Email</span>
                                <a href="mailto:{{ $contact['email'] }}" class="mt-0.5 block truncate font-semibold hover:text-light-aqua">{{ $contact['email'] }}</a>
                            </span>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-light-aqua"><flux:icon.map-pin variant="mini" class="size-5" /></span>
                            <span>
                                <span class="block text-xs uppercase tracking-widest text-light-aqua">Office</span>
                                <address class="mt-0.5 font-semibold not-italic">
                                    @foreach ($contact['address'] as $line)
                                        {{ $line }}@if (! $loop->last)<br>@endif
                                    @endforeach
                                </address>
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="mk-card p-7">
                    <h2 class="flex items-center gap-2 text-lg font-semibold text-navy dark:text-white">
                        <flux:icon.clock variant="mini" class="size-5 text-teal dark:text-light-aqua" /> Business hours
                    </h2>
                    <dl class="mt-4 divide-y divide-teal/10 text-sm dark:divide-white/10">
                        @foreach ($contact['hours'] as $days => $hours)
                            <div class="flex justify-between gap-4 py-3">
                                <dt class="text-zinc-600 dark:text-zinc-300">{{ $days }}</dt>
                                <dd class="font-medium text-navy dark:text-white">{{ $hours }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">East Africa Time (EAT, UTC+3)</p>
                </div>
            </aside>
        </div>
    @endcomponent
@endsection
