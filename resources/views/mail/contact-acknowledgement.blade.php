<x-mail::message>
<p class="eyebrow">Message received</p>

# Thanks, {{ $firstName }}

We have your message and someone from the {{ config('app.name') }} team will reply within **one working day**.

<x-mail::panel>
**Our hours:**<br>
@foreach (config('marketing.contact.hours', []) as $days => $hours)
{{ $days }}: {{ $hours }}<br>
@endforeach
East Africa Time (EAT, UTC+3)
</x-mail::panel>

While you wait, you can see what {{ config('app.name') }} does and how plans are priced.

<x-mail::button :url="route('marketing.services')">
Explore our services
</x-mail::button>

If something is urgent, call us on {{ config('marketing.contact.phone') }} or simply reply to this email.

Regards,<br>
The {{ config('app.name') }} team

<x-slot:subcopy>
You are receiving this because this address was entered on our contact form on {{ $contactMessage->created_at->format('d M Y') }}. If that was not you, you can ignore this email — we will not contact you again.
</x-slot:subcopy>
</x-mail::message>
