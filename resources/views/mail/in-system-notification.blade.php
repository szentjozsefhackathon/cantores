<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <h1>{{ $reply ? __('New reply to a notification') : __('New notification') }}</h1>

    <p>
        {{ __('Hello :name,', ['name' => $recipient->display_name ?: $recipient->name]) }}
    </p>

    <p>
        {{ $reply
            ? __('A notification thread you are part of has a new reply.')
            : __('You received a new notification in Cantores.hu.') }}
    </p>

    <table border="1" cellpadding="8" style="border-collapse: collapse;">
        <tr>
            <td><strong>{{ __('Type') }}</strong></td>
            <td>
                {{ $notification->type === \App\Enums\NotificationType::CONTACT_MESSAGE
                    ? __('Contact message')
                    : __('Error report') }}
            </td>
        </tr>
        @php
            $senderForDisplay = $messageSender ?? $reply?->user ?? $notification->reporter;
        @endphp

        @if ($senderForDisplay)
            <tr>
                <td><strong>{{ __('From') }}</strong></td>
                <td>{{ $senderForDisplay->display_name ?: $senderForDisplay->name }}</td>
            </tr>
        @endif
        @if ($notification->resource_title)
            <tr>
                <td><strong>{{ __('Resource') }}</strong></td>
                <td>{{ $notification->resource_title }}</td>
            </tr>
        @endif
        <tr>
            <td><strong>{{ __('Message') }}</strong></td>
            <td>{{ $notification->message }}</td>
        </tr>
        @if ($reply)
            <tr>
                <td><strong>{{ __('Reply') }}</strong></td>
                <td>{{ $reply->body }}</td>
            </tr>
        @endif
    </table>

    <p>
        <a href="{{ route('notifications') }}">{{ __('View notifications') }}</a>
    </p>

    <p>{{ __('You can turn off these emails in your profile settings.') }}</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
