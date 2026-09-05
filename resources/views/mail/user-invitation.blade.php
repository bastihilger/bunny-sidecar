<x-mail::message>
# {{ __('You have been invited') }}

{{ __(':inviter has invited you to manage DNS records on :app.', [
    'inviter' => $invitation->invitedBy?->name ?? $appName,
    'app' => $appName,
]) }}

{{ __('Click the button below to set up your account. The invitation expires on :date.', [
    'date' => $invitation->expires_at->toFormattedDateString(),
]) }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('If you did not expect this invitation, you can ignore this email.') }}

{{ $appName }}
</x-mail::message>
