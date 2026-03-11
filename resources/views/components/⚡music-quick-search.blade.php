<?php

use App\Models\Collection;
use App\Models\Music;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $query = '';

    #[Computed]
    public function searchUrl(): string
    {
        $q     = trim($this->query);
        $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

        $params = [];

        // Detect collection abbreviation word (same logic as results())
        $matchedCollection = Collection::where(function ($cq) use ($words) {
            foreach ($words as $word) {
                $cq->orWhere('abbreviation', $word);
            }
        })->first();

        if ($matchedCollection) {
            $params['collection'] = $matchedCollection->abbreviation;
            $titleWords = array_values(
                array_filter($words, fn ($w) => $w !== $matchedCollection->abbreviation)
            );
        } else {
            $titleWords = $words;
        }

        if ($titleWords) {
            $params['search'] = implode(' ', $titleWords);
        }

        return route('musics', $params);
    }

    #[Computed]
    public function results(): SupportCollection
    {
        $q = trim($this->query);

        if (mb_strlen($q) < 2) {
            return collect();
        }

        $results = collect();

        // Priority 1: abbreviation + order number pattern, e.g. "KÉK 191"
        if (preg_match('/^([^\d\s]+)\s+(\d+)$/u', $q, $matches)) {
            $results = Music::public()
                ->join('music_collection', 'musics.id', '=', 'music_collection.music_id')
                ->join('collections', 'collections.id', '=', 'music_collection.collection_id')
                ->where('collections.abbreviation', 'like', $matches[1])
                ->where('music_collection.order_number', $matches[2])
                ->select('musics.*')
                ->with(['collections'])
                ->limit(5)
                ->get();
        }

        // Fill remaining slots with title search
        $remaining = 5 - $results->count();
        if ($remaining > 0) {
            $words      = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
            $excludeIds = $results->pluck('id')->all();

            // Priority 2: if any word exactly matches a collection abbreviation,
            // use it as a collection filter and search the rest as title words.
            // e.g. "Szent vagy KÉK" → search "Szent vagy" within the KÉK collection
            $collectionId = null;
            $titleWords   = $words;

            $matchedCollection = Collection::where(function ($cq) use ($words) {
                foreach ($words as $word) {
                    $cq->orWhere('abbreviation', $word);
                }
            })->first();

            if ($matchedCollection) {
                $collectionId = $matchedCollection->id;
                $titleWords   = array_values(
                    array_filter($words, fn ($w) => $w !== $matchedCollection->abbreviation)
                );
            }

            $byTitle = Music::public()
                ->when($collectionId, fn ($q) => $q->whereHas('collections', fn ($cq) => $cq->where('collections.id', $collectionId)))
                ->when($titleWords, fn ($q) => $q->where(function ($query) use ($titleWords) {
                    foreach ($titleWords as $word) {
                        $query->where('titles', 'ilike', '%'.$word.'%');
                    }
                }))
                ->when($excludeIds, fn ($q) => $q->whereNotIn('musics.id', $excludeIds))
                ->with(['collections'])
                ->when(
                    $titleWords,
                    fn ($q) => $q->orderByRaw(
                        'GREATEST('.implode(', ', array_fill(0, count($titleWords), 'similarity(titles, ?)')).') DESC',
                        $titleWords
                    ),
                    fn ($q) => $q->orderBy('title')
                )
                ->limit($remaining)
                ->get();

            $results = $results->merge($byTitle);
        }

        return $results;
    }
}
?>

<div
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative w-full max-w-xl mx-auto"
>
    <div class="relative">
        <flux:input
            wire:model.live.debounce.250ms="query"
            x-on:focus="open = true"
            x-on:input="open = true"
            placeholder="Gyorskeresés..."
            icon="magnifying-glass"
            clearable
            autocomplete="off"
            class="w-full bg-white/95! dark:bg-zinc-900/95! shadow-lg"
        />
    </div>

    @if(mb_strlen(trim($query)) >= 2)
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute z-50 w-full bottom-full mb-1.5 bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xl overflow-hidden"
    >
        @forelse($this->results as $music)
        <a
            href="{{ route('music-view', $music) }}"
            wire:navigate
            class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50 dark:hover:bg-zinc-700/60 transition group"
        >
            <flux:icon name="music" variant="mini" class="h-4 w-4 text-indigo-400 shrink-0" />
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-indigo-700 dark:group-hover:text-indigo-300">
                    {{ $music->title }}
                </div>
                @if($music->subtitle)
                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $music->subtitle }}</div>
                @endif
            </div>
            @if($first = $music->collections->first())
            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500 font-mono">
                {{ $first->abbreviation ?? $first->title }}
            </span>
            @endif
        </a>
        @empty
        <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 italic">
            Nincs találat.
        </div>
        @endforelse

        <a
            href="{{ $this->searchUrl }}"
            wire:navigate
            class="flex items-center gap-2 px-4 py-2.5 border-t border-gray-100 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900/50 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-zinc-700 transition"
        >
            <flux:icon name="arrow-right" variant="mini" class="h-4 w-4" />
            Keresés az összes találat között
        </a>
    </div>
    @endif
</div>
