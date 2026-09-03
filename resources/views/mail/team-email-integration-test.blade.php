<x-mail::message>
# Email delivery is connected

This test confirms that {{ $provider->label() }} can send email for {{ $team->name }}.

No further action is needed. You can return to your workspace and start sending.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
