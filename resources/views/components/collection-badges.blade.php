@props(['music', 'limit' => 3, 'tooltip' => true])

@php
    $rankedCollections = $music->displayCollections(auth()->user());
    $shownCollections = $rankedCollections->take($limit);
    $hiddenCollections = $rankedCollections->slice($limit);
@endphp

@foreach($shownCollections as $collection)
    <x-collection-badge :collection="$collection" :tooltip="$tooltip" />
@endforeach

@if($hiddenCollections->isNotEmpty())
    @php
        $hiddenLabel = $hiddenCollections->map(fn ($collection) => $collection->formatWithPivot($collection->pivot))->join(', ');
    @endphp
    @if($tooltip)
        <flux:tooltip content="{{ $hiddenLabel }}">
            <flux:badge size="sm" color="zinc" class="relative z-10">+{{ $hiddenCollections->count() }}</flux:badge>
        </flux:tooltip>
    @else
        <flux:badge size="sm" color="zinc" class="relative z-10" title="{{ $hiddenLabel }}">+{{ $hiddenCollections->count() }}</flux:badge>
    @endif
@endif
