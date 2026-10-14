@extends('layouts.marketing')

@section('title', 'Privacy policy')
@section('meta_description', 'What personal information Kaba Water collects, why, and the choices you have.')

@section('content')
    @include('marketing.partials.page-hero', [
        'eyebrow' => 'Legal',
        'title' => 'Privacy policy',
        'lead' => 'Last updated 14 October 2026',
    ])

    <div class="bg-white dark:bg-navy-deep">
        @component('marketing.partials.prose')
            <p>
                This policy explains what personal information Kaba Water collects, how we use it and the choices you have. It covers this website and the Kaba Water billing service.
            </p>

            <h2>1. Who is responsible</h2>
            <p>
                For information about our own customers and website visitors, Kaba Water decides how it is used. For information a water vendor enters about <strong>their</strong> customers (names, meters, readings, bills), the vendor decides how it is used and we process it on their instructions.
            </p>

            <h2>2. What we collect</h2>
            <ul>
                <li><strong>Contact form:</strong> your name, email address, company and message.</li>
                <li><strong>Account details:</strong> name, email address, role and sign-in records for people who use a workspace.</li>
                <li><strong>Billing records:</strong> client names and contact details, meter and reading data, invoices and payments entered by a vendor.</li>
                <li><strong>Meter photos:</strong> images submitted for reading capture, used to read the dial.</li>
                <li><strong>Technical data:</strong> basic logs needed to keep the service secure and working.</li>
            </ul>

            <h2>3. How we use it</h2>
            <ul>
                <li>To reply to enquiries and provide the service you have asked for.</li>
                <li>To produce invoices, statements and reports for the vendor's workspace.</li>
                <li>To keep accounts secure, prevent misuse and fix problems.</li>
                <li>To tell workspace owners about important changes to the service.</li>
            </ul>
            <p>We do not sell personal information, and we do not use vendors' customer data for advertising.</p>

            <h2>4. Who we share it with</h2>
            <p>
                We share information only with the providers that help us run the service, such as hosting, email delivery and the image-recognition service that reads meter photos, and only as far as they need it to do that job. We may also disclose information where the law requires it.
            </p>

            <h2>5. How long we keep it</h2>
            <p>
                Contact-form messages are kept for as long as needed to deal with your enquiry. Workspace data is kept while the workspace is active and for a limited period afterwards so the vendor can export it, unless the law requires us to keep records for longer.
            </p>

            <h2>6. Your rights</h2>
            <p>
                Under the Kenya Data Protection Act, 2019 you can ask to see the personal information held about you, have it corrected or deleted, and object to how it is used. If your water vendor uses Kaba Water, contact them first, as they hold your billing records. For anything we hold directly, <a href="{{ route('marketing.contact') }}" wire:navigate>contact us</a>.
            </p>

            <h2>7. Cookies</h2>
            <p>
                We use only the cookies needed to keep you signed in, protect forms from misuse and remember your light or dark display preference. We do not use advertising or tracking cookies.
            </p>

            <h2>8. Changes</h2>
            <p>
                If we change this policy we will update the date at the top of the page, and tell workspace owners by email if the change is significant.
            </p>
        @endcomponent
    </div>
@endsection
