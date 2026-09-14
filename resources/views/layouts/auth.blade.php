@props([
    'title' => null,
    'logo' => true,
    'noindex' => false,
])

<x-layouts::auth.simple :title="$title" :logo="$logo" :noindex="$noindex">
    {{ $slot }}
</x-layouts::auth.simple>
