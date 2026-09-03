<x-mail::message>
# Verify your sender address

You requested to use **{{ $sender->email }}** as a sender in {{ config('app.name') }}.

<x-mail::button :url="$verificationUrl">
Verify sender address
</x-mail::button>

If you did not request this, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
