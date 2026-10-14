<x-mail::message>
<p class="eyebrow">Team invitation</p>

# Welcome to {{ $workspace }}, {{ \Illuminate\Support\Str::before($name, ' ') }}

{{ $inviterName }} has added you to the team as **{{ $roleLabel }}**. Set a password to sign in and get started.

<x-mail::button :url="$url">
Set my password
</x-mail::button>

<x-mail::panel>
This link expires in {{ config('auth.passwords.users.expire', 60) }} minutes. If it has expired, use **Forgot your password?** on the sign-in page.
</x-mail::panel>

Regards,<br>
The {{ $workspace }} team

<x-slot:subcopy>
If the button does not work, copy and paste this link into your browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
