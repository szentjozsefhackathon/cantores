@props(['genreId' => null])

@php
    $iconName = $genreId
        ? (\App\Models\Genre::findCached($genreId)?->icon() ?? 'musical-note')
        : 'musical-note';
@endphp

<div>
    @switch($iconName)
        @case('organist')
            <flux:icon name="organist" class="h-10 w-10" />
            @break
        @case('guitar')
            <flux:icon name="guitar" class="h-10 w-10" />
            @break
        @case('other')
            <flux:icon name="genre_other" />
            @break
        @default
            <flux:icon name="list-music" class="h-10 w-10" variant="outline" />
    @endswitch
</div>
