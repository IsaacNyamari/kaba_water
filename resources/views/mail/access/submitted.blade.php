<x-mail::message>
<p class="eyebrow">Needs review</p>

# New access request

{{ $accessRequest->name }} asked for access as a **{{ strtolower($accessRequest->typeLabel()) }}**{{ $accessRequest->wantsWorkspace() ? ' (own workspace)' : ' (portal account)' }}.

<x-mail::table>
| | |
|:--|:--|
| **Name** | {{ $accessRequest->name }} |
| **Email** | [{{ $accessRequest->email }}](mailto:{{ $accessRequest->email }}) |
| **Phone** | {{ $accessRequest->phone ?: '—' }} |
| **Organisation** | {{ $accessRequest->organization ?: '—' }} |
| **Email check** | {{ $accessRequest->email_check?->label() ?? 'Not run' }} |
</x-mail::table>

<x-mail::button :url="$reviewUrl">
Review requests
</x-mail::button>

<p class="muted">The request stays pending until an admin approves or rejects it.</p>
</x-mail::message>
