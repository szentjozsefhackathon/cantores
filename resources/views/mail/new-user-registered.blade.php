<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <h1>{{ __('New user registered') }}</h1>

    <p>{{ __('A new user has registered on the platform.') }}</p>

    <table border="1" cellpadding="8" style="border-collapse: collapse;">
        <tr>
            <td><strong>{{ __('Registered at') }}</strong></td>
            <td>{{ $user->created_at->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <p>
        <a href="{{ route('admin.users') }}">{{ __('View users') }}</a>
    </p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
