@props([
    'title' => null,
    'logo' => true,
])

<x-layouts::auth.simple :title="$title" :logo="$logo">
    {{ $slot }}
</x-layouts::auth.simple>
