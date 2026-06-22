@props([
    'music',
    'score' => null,
    'scopeLabel' => null,
    'scoreReasons' => [],
    'privateShare' => false,
    'shareScores' => [],
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden max-w-[355px] relative group transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-2xl']) }}
     style="box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08), 0 4px 8px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.12);"
>
    @can('view', $music)
    <a href="{{ route('music-view', $music) }}" class="absolute inset-0 z-0" aria-label="{{ $music->title }}" wire:navigate></a>
    @endcan
    <!-- Relevance score stars -->
    @if($score !== null)
        @php
            $stars = $score >= 17 ? 4 : ($score >= 11 ? 3 : ($score >= 6 ? 2 : 1));
        @endphp
        <div class="absolute top-1 right-1 z-20" x-data="{ showRelevance: false }">
            <button
                type="button"
                x-ref="relevanceTrigger"
                x-on:click.prevent.stop="showRelevance = !showRelevance"
                class="flex flex-row items-center gap-0.5 rounded px-1 py-0.5 transition-colors hover:bg-amber-100/70 dark:hover:bg-amber-900/40"
                :aria-expanded="showRelevance"
                title="{{ __('Why this relevance score?') }}"
            >
                @for ($i = 0; $i < $stars; $i++)
                    <flux:icon name="star" class="h-3 w-3 fill-amber-400 text-amber-400" />
                @endfor
                <flux:icon name="information-circle" class="h-3.5 w-3.5 text-amber-500 dark:text-amber-400" />
            </button>
            <template x-teleport="body">
                <div
                    x-show="showRelevance"
                    x-cloak
                    x-anchor.bottom-end.offset.6="$refs.relevanceTrigger"
                    x-transition.origin.top.right
                    x-on:click.outside="showRelevance = false"
                    x-on:keydown.escape.window="showRelevance = false"
                    class="z-50 w-64 rounded-lg border border-gray-200 bg-white p-3 text-left shadow-xl dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ __('Relevance') }}</span>
                        <span class="inline-flex items-center gap-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <flux:icon name="star" class="h-3 w-3 fill-amber-400 text-amber-400" />
                            {{ __(':score points', ['score' => $score]) }}
                        </span>
                    </div>
                    @if(!empty($scoreReasons))
                        <ul class="space-y-1">
                            @foreach($scoreReasons as $reason)
                                <li class="flex items-start justify-between gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    <span>{{ $reason['label'] }}</span>
                                    <span class="shrink-0 font-medium text-amber-600 dark:text-amber-400">+{{ $reason['points'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('This suggestion comes from a music plan for a related celebration.') }}
                        </p>
                    @endif
                </div>
            </template>
        </div>
    @endif
    <!-- Bottom right corner rounded rectangle with genre icons -->
    <div class="absolute bottom-0 right-0 pointer-events-none flex items-center justify-center gap-1 px-2 py-1 rounded-tl-md bg-gray-200/30 dark:bg-gray-700/30 backdrop-blur-sm">
        @foreach($music->genres as $genre)
            <flux:icon name="{{ $genre->icon() }}" class="h-4 w-4 flex-shrink-0 text-zinc-600 dark:text-zinc-300" />
        @endforeach
    </div>
    <!-- Header with title and custom ID -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                     {{ $music->title }}
                </h3>
                @if($music->subtitle)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $music->subtitle }}
                    </p>
                @endif

                    <div class="mt-1 flex flex-wrap gap-1">
                        @if(!empty($scopeLabel))
                        <flux:badge color="amber" size="sm">{{ $scopeLabel }}</flux:badge>
                        @endif
                        @if($music->custom_id)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            {{ $music->custom_id }}
                        </span>
                        @endif
                        <x-collection-badges :music="$music" />
                        @foreach($music->tags as $tag)
                            <x-music-tag-badge :tag="$tag" />
                        @endforeach
                    </div>
            </div>
            <div class="relative z-10 flex items-center gap-1">
                <div class="flex flex-col items-center gap-1">
                @can('update', $music)
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="pencil"
                        :href="route('music-editor', $music)"
                        wire:navigate
                        :title="__('Edit')"
                        class="!p-1"
                    />
                @endcan
                </div>
            </div>
        </div>
    </div>

    @php
        $incipitScores = $privateShare
            ? collect()
            : $music->visibleIncipitScores(auth()->user())
                ->map(fn($s) => ['score' => $s, 'url' => $s->public_preview ? $s->publicIncipitUrl() : $s->incipitUrl()]);
    @endphp
    @if($incipitScores->isNotEmpty())
    <div class="border-b border-gray-200 dark:border-gray-700 px-3 py-2"
         x-data="{ current: 0, total: {{ $incipitScores->count() }} }">
        <div class="flex items-center gap-1">
            @if($incipitScores->count() > 1)
            <button
                x-on:click.prevent="current = (current - 1 + total) % total"
                class="relative z-10 shrink-0 p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded transition-colors"
                :title="'{{ __('Previous') }}'">
                <flux:icon name="chevron-left" class="h-4 w-4" />
            </button>
            @endif
            <div class="flex-1 min-w-0 overflow-hidden max-h-20">
                @foreach($incipitScores as $i => $incipitItem)
                <div x-show="current === {{ $i }}" @if($i > 0) x-cloak @endif>
                    <x-incipit-image class="max-w-full"
                         :src="$incipitItem['url']"
                         :alt="$incipitItem['score']->title"
                         img-class="block h-auto max-h-14 w-auto max-w-full" />
                </div>
                @endforeach
            </div>
            @if($incipitScores->count() > 1)
            <button
                x-on:click.prevent="current = (current + 1) % total"
                class="relative z-10 shrink-0 p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded transition-colors"
                :title="'{{ __('Next') }}'">
                <flux:icon name="chevron-right" class="h-4 w-4" />
            </button>
            @endif
        </div>
    </div>
    @endif

    @if($privateShare && !empty($shareScores))
    <div class="border-b border-gray-200 dark:border-gray-700 px-4 py-3 space-y-3">
        @foreach($shareScores as $shareScore)
        <div class="space-y-1">
            @if(!empty($shareScore['share_url']))
            <a href="{{ $shareScore['share_url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="relative z-10 inline-flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline">
                {{ $shareScore['title'] }}
                <flux:badge size="sm" color="zinc">{{ $shareScore['format'] }}</flux:badge>
                <flux:icon name="arrow-top-right-on-square" variant="micro" class="text-gray-400" />
            </a>
            @else
            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                {{ $shareScore['title'] }}
                <flux:badge size="sm" color="zinc">{{ $shareScore['format'] }}</flux:badge>
            </span>
            @endif

            @if(!empty($shareScore['incipit_url']))
            <x-incipit-image class="max-w-full"
                 :src="$shareScore['incipit_url']"
                 :alt="$shareScore['title']"
                 img-class="block h-auto max-h-14 w-auto max-w-full" />
            @endif

            @if(!empty($shareScore['urls']))
            <div class="flex flex-wrap gap-2">
                @foreach($shareScore['urls'] as $scoreUrl)
                    <a href="{{ $scoreUrl['url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="relative z-10 inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline"
                       title="{{ $scoreUrl['label'] }}"
                    >
                        <flux:icon name="{{ $scoreUrl['icon'] ?? 'link' }}" class="size-3.5 shrink-0 {{ $scoreUrl['color'] ?? 'text-gray-500' }}" />
                        {{ $scoreUrl['host'] ?? $scoreUrl['label'] }}
                        @if(!empty($scoreUrl['comment']))
                            <span class="text-gray-500 dark:text-gray-400">({{ $scoreUrl['comment'] }})</span>
                        @endif
                    </a>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    @if($music->authors->isNotEmpty() || $music->urls->isNotEmpty() || $music->scriptureReferences->isNotEmpty() || $music->allMusicRelations()->isNotEmpty())
    <div class="px-4 py-3 space-y-2">
        @if($music->authors->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($music->authors as $author)
                <a href="{{ route('author-view', $author) }}"
                   class="relative z-10 flex items-center gap-1.5 px-2 py-1 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-gray-700 dark:text-gray-300"
                   wire:navigate
                >
                    @if($author->avatarThumbUrl())
                        <img src="{{ $author->avatarThumbUrl() }}" alt="{{ $author->name }}"
                             class="w-5 h-5 rounded object-cover shrink-0" />
                    @else
                        <div class="w-5 h-5 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center shrink-0">
                            <flux:icon name="user" class="w-3 h-3 text-gray-400 dark:text-gray-500" />
                        </div>
                    @endif
                    <span class="text-xs font-medium">{{ $author->name }}</span>
                </a>
            @endforeach
        </div>
        @endif

        @if($music->urls->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($music->urls as $url)
                @php
                    $urlLabelEnum = \App\MusicUrlLabel::tryFrom($url->label);
                    $urlIcon = $urlLabelEnum?->icon() ?? 'link';
                    $urlColor = $urlLabelEnum?->color() ?? 'text-gray-500';
                    $urlHost = preg_replace('/^www\./', '', parse_url($url->url, PHP_URL_HOST) ?? $url->url);
                @endphp
                <a href="{{ $url->url }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="relative z-10 inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline"
                   title="{{ $urlLabelEnum?->label() ?? $url->url }}"
                >
                    <flux:icon name="{{ $urlIcon }}" class="size-3.5 shrink-0 {{ $urlColor }}" />
                    {{ $urlHost }}
                </a>
            @endforeach
        </div>
        @endif

        @if($music->scriptureReferences->isNotEmpty())
        <div class="flex items-start gap-2">
            <flux:icon name="book-open-text" class="size-4 shrink-0 mt-0.5 text-gray-400 dark:text-gray-500" />
            <div class="flex flex-wrap gap-1">
                @foreach($music->scriptureReferences as $scriptureReference)
                    <a href="https://szentiras.eu/{{ rawurlencode($scriptureReference->reference) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="relative z-10 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                       title="{{ $scriptureReference->reference_type->label() }}"
                    >
                        {{ $scriptureReference->reference }}
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        @if($music->allMusicRelations()->isNotEmpty())
        <div class="flex items-start gap-2">
            <flux:icon name="link" class="size-4 shrink-0 mt-0.5 text-gray-400 dark:text-gray-500" />
            <div class="flex flex-wrap gap-1">
                @foreach($music->allMusicRelations() as $relation)
                @php $partner = $relation->partnerFor($music); @endphp
                    @can('view', $partner)
                    <div class="relative z-10 inline-flex flex-col gap-1">
                        <a href="{{ route('music-view', $partner) }}"
                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                           wire:navigate
                        >
                            {{ $partner->title }}
                            @if($relation->relationship_type)
                                <span class="text-gray-400">({{ \App\MusicRelationshipType::from($relation->relationship_type)->label() }})</span>
                            @endif
                        </a>
                        @if($partner->collections->isNotEmpty())
                            <div class="flex flex-wrap gap-0.5 pl-2">
                                <x-collection-badges :music="$partner" />
                            </div>
                        @endif
                    </div>
                    @endcan
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif
</div>
