<x-mail::message>
<p class="eyebrow">Access approved</p>

# Welcome, {{ $accessRequest->name }}

Your request for access{{ $workspace ? " to {$workspace}" : '' }} has been approved. Set your password to finish creating your {{ config('app.name') }} account.

<x-mail::button :url="$url">
Create my account
</x-mail::button>

<x-mail::panel>
This link works **once** and expires on **{{ $accessRequest->invite_expires_at?->format('d M Y \a\t H:i') }}**.
</x-mail::panel>

@if ($accessRequest->role && ! in_array($accessRequest->role, ['vendor', 'client', 'super_admin'], true))
You will sign in as **{{ \App\Support\TeamRoles::label($accessRequest->role) }}**.
@elseif ($accessRequest->role === 'vendor' && $accessRequest->vendor?->isPropertyOwner())
Once you are in, add your houses by house number, link each tenant, set the rent and bill them every month.
@elseif ($accessRequest->role === 'vendor')
Once you are in, add your first clients and meters, set your tariff, and you are ready to capture readings.
@else
Once you are in, you can see your bills, your water usage and your payment history at any time.
@endif

<p class="muted">If you did not ask for access, you can ignore this email and nothing will be created.</p>

Regards,<br>
The {{ config('app.name') }} team

<x-slot:subcopy>
If the button does not work, copy and paste this link into your browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
