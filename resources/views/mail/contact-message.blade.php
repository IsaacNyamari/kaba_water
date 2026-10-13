<x-mail::message>
# New website enquiry

**From:** {{ $contactMessage->name }} ({{ $contactMessage->email }})

@if ($contactMessage->company)
**Company:** {{ $contactMessage->company }}

@endif
**Sent:** {{ $contactMessage->created_at->format('d M Y, H:i') }}

<x-mail::panel>
{{ $contactMessage->message }}
</x-mail::panel>

Reply to this email to answer {{ $contactMessage->name }} directly.
</x-mail::message>
