@props(['assignment'])

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden flex-1 relative">
    <!-- Bottom right corner rounded rectangle with genre icons -->
    <div class="absolute bottom-0 right-0 pointer-events-none flex items-center justify-center gap-1 px-2 py-1 rounded-tl-md bg-gray-200/30 dark:bg-gray-700/30 backdrop-blur-sm">
        @foreach($assignment['music_genres'] as $genre)
            <flux:icon :name="$genre['icon']" class="h-4 w-4 flex-shrink-0 text-zinc-600 dark:text-zinc-300" />
        @endforeach
    </div>
    <!-- Header with title and custom ID -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ $assignment['music_title'] }}
                </h3>
                @if($assignment['music_subtitle'])
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $assignment['music_subtitle'] }}
                    </p>
                @endif

                <div class="mt-1 flex flex-wrap gap-1">
                    @if($assignment['music_custom_id'])
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                        {{ $assignment['music_custom_id'] }}
                    </span>
                    @endif
                    @foreach($assignment['music_collections'] as $collection)
                        <flux:tooltip content="{{ $collection['title'] }} {{ $collection['page_number'] ? __('(p.:page)', ['page' => $collection['page_number']]) : '' }}">
                            <flux:badge size="sm">{{ $collection['abbreviation'] ?? $collection['title'] }} {{ $collection['order_number'] }}</flux:badge>
                        </flux:tooltip>
                    @endforeach
                    @foreach($assignment['music_tags'] as $tag)
                        <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                            <flux:icon :name="$tag['icon']" class="h-3 w-3" />
                            <span>{{ $tag['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center gap-1">
                <div class="flex flex-col items-center gap-1">
                    @if($assignment['can_view_music'])
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="eye"
                            :href="route('music-view', $assignment['music_id'])"
                            target="_blank"
                            :title="__('View')"
                            class="!p-1"
                        />
                    @endif
                    @if($assignment['can_edit_music'])
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="pencil"
                            :href="route('music-editor', $assignment['music_id'])"
                            target="_blank"
                            :title="__('Edit')"
                            class="!p-1"
                        />
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if((isset($assignment['music_authors']) && !empty($assignment['music_authors'])) || (isset($assignment['music_urls']) && !empty($assignment['music_urls'])) || (isset($assignment['music_relations']) && !empty($assignment['music_relations'])))
    <div class="px-4 py-3 space-y-2">
        @if(isset($assignment['music_authors']) && !empty($assignment['music_authors']))
        <div class="flex flex-wrap gap-2">
            @foreach($assignment['music_authors'] as $author)
                @php $authorName = $author['name'] ?? $author; $authorThumb = $author['avatar_thumb_url'] ?? null; @endphp
                <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                    @if($authorThumb)
                        <img src="{{ $authorThumb }}" alt="{{ $authorName }}"
                             class="w-5 h-5 rounded object-cover shrink-0" />
                    @else
                        <div class="w-5 h-5 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center shrink-0">
                            <flux:icon name="user" class="w-3 h-3 text-gray-400 dark:text-gray-500" />
                        </div>
                    @endif
                    <span class="text-xs font-medium">{{ $authorName }}</span>
                </div>
            @endforeach
        </div>
        @endif

        @if(isset($assignment['music_urls']) && !empty($assignment['music_urls']))
        <div class="flex items-start gap-2">
            <flux:icon name="arrow-top-right-on-square" class="size-4 shrink-0 mt-0.5 text-gray-400 dark:text-gray-500" />
            <div class="flex flex-wrap gap-2">
                @foreach($assignment['music_urls'] as $url)
                    <a href="{{ $url['url'] ?? $url }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                       title="{{ $url['label'] ?? ($url['url'] ?? $url) }}"
                    >{{ $url['label'] ?? ($url['url'] ?? $url) }}</a>
                @endforeach
            </div>
        </div>
        @endif

        @if(isset($assignment['music_relations']) && !empty($assignment['music_relations']))
        <div class="flex items-start gap-2">
            <flux:icon name="link" class="size-4 shrink-0 mt-0.5 text-gray-400 dark:text-gray-500" />
            <div class="flex flex-wrap gap-1">
                @foreach($assignment['music_relations'] as $relation)
                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                        {{ $relation['title'] ?? $relation }}
                        @if(isset($relation['relationship_type']))
                            <span class="text-gray-400">({{ $relation['relationship_type'] }})</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif
</div>
