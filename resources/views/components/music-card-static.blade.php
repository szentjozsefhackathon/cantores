@props(['assignment'])

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden max-w-[355px]">
    <!-- Header with title and custom ID -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
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
                    <div class="flex flex-col items-center gap-1">
                        @foreach($assignment['music_genres'] as $genre)
                            <flux:icon :name="$genre['icon']" class="h-5 w-5 flex-shrink-0 text-zinc-600 dark:text-zinc-300" />
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
