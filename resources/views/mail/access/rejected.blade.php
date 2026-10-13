<x-mail::message>
<p class="eyebrow">Access request</p>

# Hello {{ $accessRequest->name }},

Thank you for your interest in {{ config('app.name') }}. Unfortunately we could not approve your access request this time.

@if ($accessRequest->rejection_reason)
<x-mail::panel>
**Reason:** {{ $accessRequest->rejection_reason }}
</x-mail::panel>
@endif

If you think this is a mistake, or your details have changed, reply to this email and we will take another look.

Regards,<br>
The {{ config('app.name') }} team
</x-mail::message>
