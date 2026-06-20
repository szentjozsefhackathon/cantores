@props(['music', 'limit' => 3])

@php
    $rankedCollections = $music->displayCollections(auth()->user());
    $shownCollections = $rankedCollections->take($limit);
    $hiddenCollections = $rankedCollections->slice($limit);
@endphp

@foreach($shownCollections as $collection)
    <x-collection-badge :collection="$collection" />
@endforeach

@if($hiddenCollections->isNotEmpty())
    <flux:tooltip content="{{ $hiddenCollections->map(fn ($collection) => $collection->formatWithPivot($collection->pivot))->join(', ') }}">
        <flux:badge size="sm" color="zinc" class="relative z-10">+{{ $hiddenCollections->count() }}</flux:badge>
    </flux:tooltip>
@endif
