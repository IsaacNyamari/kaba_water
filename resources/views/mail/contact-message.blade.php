<x-mail::message>
<p class="eyebrow">Website enquiry</p>

# {{ $contactMessage->name }} sent a message

<x-mail::table>
| | |
|:--|:--|
| **Name** | {{ $contactMessage->name }} |
| **Email** | [{{ $contactMessage->email }}](mailto:{{ $contactMessage->email }}) |
| **Company** | {{ $contactMessage->company ?: '—' }} |
| **Received** | {{ $contactMessage->created_at->format('d M Y, H:i') }} |
</x-mail::table>

<x-mail::panel>
{!! nl2br(e($contactMessage->message)) !!}
</x-mail::panel>

<x-mail::button :url="'mailto:'.$contactMessage->email">
Reply to {{ \Illuminate\Support\Str::before($contactMessage->name, ' ') }}
</x-mail::button>

<p class="muted">Replying to this email also goes straight to {{ $contactMessage->name }}.</p>
</x-mail::message>
