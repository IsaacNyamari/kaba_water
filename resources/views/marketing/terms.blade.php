@extends('layouts.marketing')

@section('title', 'Terms of service')
@section('meta_description', 'The terms that apply when you use HS Water Billing to bill your water customers.')

@section('content')
    @include('marketing.partials.page-hero', [
        'eyebrow' => 'Legal',
        'title' => 'Terms of service',
        'lead' => 'Last updated 14 October 2026',
    ])

    <div class="bg-white dark:bg-navy-deep">
        @component('marketing.partials.prose')
            <p>
                These terms apply when you use HS Water Billing, the water-utility billing service. By creating a workspace or signing in, you agree to them. If you are accepting on behalf of a business, you confirm you are authorised to do so.
            </p>

            <h2>1. The service</h2>
            <p>
                HS Water Billing lets a water vendor record clients, meters and readings, issue invoices, record payments and produce financial reports. We may improve or change features over time, and will give reasonable notice of any change that removes something you rely on.
            </p>

            <h2>2. Your account</h2>
            <ul>
                <li>Access is by approval. You must give accurate details when requesting it.</li>
                <li>You are responsible for the people you invite and what they do in your workspace.</li>
                <li>Keep your sign-in details private and tell us promptly if you think an account has been misused.</li>
            </ul>

            <h2>3. Your data</h2>
            <p>
                The client, meter, billing and financial records you enter belong to you. You give us permission to store and process them only so that we can provide the service. You can export your records at any time. How we handle personal information is described in our <a href="{{ route('marketing.privacy') }}" wire:navigate>privacy policy</a>.
            </p>

            <h2>4. Your responsibilities</h2>
            <ul>
                <li>You are responsible for the accuracy of the readings, tariffs and charges you enter, and for the invoices you send.</li>
                <li>You must have a lawful basis for holding your customers' details and must tell them how those details are used.</li>
                <li>You must not use the service to break the law, or try to access another vendor's workspace.</li>
            </ul>

            <h2>5. Fees</h2>
            <p>
                Paid plans are billed in advance, monthly or annually, at the prices shown on our <a href="{{ route('marketing.pricing') }}" wire:navigate>pricing page</a> or agreed with you in writing. Prices exclude taxes. If a payment is overdue we may suspend the workspace after giving notice; your data is kept so you can pay and continue, or export it.
            </p>

            <h2>6. Cancelling</h2>
            <p>
                You can cancel at any time. Your plan runs to the end of the period you have paid for. We may end or suspend an account that seriously breaches these terms.
            </p>

            <h2>7. Availability and liability</h2>
            <p>
                We work to keep the service available and your data safe, but we do not promise it will be uninterrupted or free of errors. HS Water Billing is a tool for running your billing; it is not tax, legal or accounting advice. To the extent the law allows, our total liability to you is limited to the fees you paid us in the twelve months before the claim.
            </p>

            <h2>8. Changes to these terms</h2>
            <p>
                We may update these terms. If a change is significant we will tell workspace owners by email before it takes effect. Continuing to use the service after that date means you accept the new terms.
            </p>

            <h2>9. Contact</h2>
            <p>
                Questions about these terms? <a href="{{ route('marketing.contact') }}" wire:navigate>Get in touch</a> and we will be glad to explain.
            </p>
        @endcomponent
    </div>
@endsection
