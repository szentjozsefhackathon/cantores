@php
    $rankedCollections = $music->displayCollections(auth()->user());
    $hiddenCollections = $rankedCollections->slice(3);
@endphp
<div class="flex flex-wrap items-center gap-2">
    @forelse ($rankedCollections->take(3) as $collection)
        <span class="inline-flex items-center font-medium max-w-full whitespace-normal wrap-anywhere text-xs py-1 rounded-md px-2 text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40">{{ $collection->formatWithPivot($collection->pivot) }}</span>
    @empty
        <span class="text-gray-400 dark:text-gray-500 text-sm">{{ __('None') }}</span>
    @endforelse
    @if($hiddenCollections->isNotEmpty())
        <flux:tooltip content="{{ $hiddenCollections->map(fn ($collection) => $collection->formatWithPivot($collection->pivot))->join(', ') }}">
            <span class="inline-flex items-center font-medium max-w-full whitespace-normal wrap-anywhere text-xs py-1 rounded-md px-2 text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40">+{{ $hiddenCollections->count() }}</span>
        </flux:tooltip>
    @endif
</div>
