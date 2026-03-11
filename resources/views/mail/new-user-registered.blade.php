<x-mail::message>
# {{ __('New user registered') }}

{{ __('A new user has registered on the platform.') }}

<x-mail::table>
| | |
|:--|:--|
| **{{ __('Name') }}** | {{ $user->name }} |
| **{{ __('Email') }}** | {{ $user->email }} |
| **{{ __('Registered at') }}** | {{ $user->created_at->format('Y-m-d H:i') }} |
</x-mail::table>

<x-mail::button :url="route('admin.users')">
{{ __('View users') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
