<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Back button -->
        <div class="mb-6">
            <flux:button
                variant="ghost"
                icon="arrow-left"
                :href="route('music-plans')"
                tag="a"
            >
                {{ __('Back to Music Plans') }}
            </flux:button>
        </div>

        <!-- Music summary card -->
        <div class="mb-6">
            <livewire:music-card :music="$music" />
        </div>

        <flux:card class="p-5">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <flux:heading size="xl">{{ __('Music Piece Details') }}</flux:heading>
                    <flux:subheading>{{ $music->title }}</flux:subheading>
                    @auth
                    <flux:button variant="ghost" icon="flag" wire:click="dispatch('openErrorReportModal', { resourceId: {{ $music->id }}, resourceType: 'music' })">
                        {{ __('Report an Issue') }}
                    </flux:button>
                    @endauth
                </div>
                
                <!-- Privacy badge -->
                <flux:badge color="{{ $music->is_private ? 'zinc' : 'green' }}" size="lg">
                    {{ $music->is_private ? __('Private') : __('Public') }}
                </flux:badge>
            </div>

            <div class="space-y-6">
                <!-- Basic info -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Custom ID') }}</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $music->custom_id ?? '–' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Subtitle') }}</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $music->subtitle ?? '–' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Created by') }}</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $music->user?->display_name ?? '–' }}</flux:text>
                    </div>
                </div>

                <!-- Genres -->
                @if($music->genres->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Genres') }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($music->genres as $genre)
                            <flux:badge color="blue" size="sm" :icon="$genre->icon()">
                                {{ $genre->label() }}
                            </flux:badge>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Authors -->
                @if($music->authors->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Authors') }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($music->authors as $author)
                            <a href="{{ route('author-view', $author) }}" class="inline-block">
                                <flux:badge color="purple" size="sm" class="hover:bg-purple-600 transition-colors">
                                    {{ $author->name }}
                                </flux:badge>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Collections -->
                @if($music->collections->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Collections') }}</flux:heading>
                    <div class="space-y-3">
                        @foreach($music->collections as $collection)
                            <a href="{{ route('collection-view', $collection) }}" class="block">
                                <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <div>
                                        <flux:text class="font-medium">{{ $collection->title }}</flux:text>
                                        @if($collection->abbreviation)
                                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">({{ $collection->abbreviation }})</flux:text>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        @if($collection->pivot->page_number)
                                            {{ __('Page') }}: {{ $collection->pivot->page_number }}
                                        @endif
                                        @if($collection->pivot->order_number)
                                            {{ __('Order') }}: {{ $collection->pivot->order_number }}
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- URLs -->
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('External Links') }}</flux:heading>
                    @if($music->urls->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($music->urls as $url)
                                @php
                                    $labelColors = [
                                        'sheet_music' => 'blue',
                                        'audio' => 'green',
                                        'video' => 'purple',
                                        'text' => 'amber',
                                        'information' => 'zinc',
                                    ];
                                    $labelIcons = [
                                        'sheet_music' => 'document-text',
                                        'audio' => 'music',
                                        'video' => 'video-camera',
                                        'text' => 'book-open-text',
                                        'information' => 'information-circle',
                                    ];
                                    $labelTranslations = [
                                        'sheet_music' => __('Sheet Music'),
                                        'audio' => __('Audio'),
                                        'video' => __('Video'),
                                        'text' => __('Text'),
                                        'information' => __('Information'),
                                    ];
                                    $color = $labelColors[$url->label] ?? 'zinc';
                                    $icon = $labelIcons[$url->label] ?? 'external-link';
                                    $labelText = $labelTranslations[$url->label] ?? ucfirst(str_replace('_', ' ', $url->label));
                                @endphp
                                <a href="{{ $url->url }}" target="_blank" rel="noopener noreferrer" class="block">
                                    <flux:card class="p-4 hover:shadow-md transition-shadow" variant="outline">
                                        <div class="flex items-start gap-3">
                                            <flux:icon :name="$icon" class="h-5 w-5 text-{{ $color }}-500 shrink-0 mt-0.5" />
                                            <div class="flex-1 min-w-0">
                                                <flux:text class="font-medium text-sm truncate">{{ $labelText }}</flux:text>
                                                <flux:text class="text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $url->url }}">{{ Str::limit($url->url, 40) }}</flux:text>
                                            </div>
                                            <flux:icon name="external-link" class="h-4 w-4 text-gray-400 shrink-0 mt-0.5" />
                                        </div>
                                    </flux:card>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <flux:callout color="zinc" variant="soft">
                            <flux:text>{{ __('No external links available for this music piece.') }}</flux:text>
                        </flux:callout>
                    @endif
                </div>

                <!-- Additional info -->
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Additional Information') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <flux:icon name="calendar" class="h-4 w-4 text-gray-500" />
                            <span class="text-gray-700 dark:text-gray-300">{{ __('Created') }}: {{ $music->created_at->translatedFormat('Y-m-d') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon name="pencil" class="h-4 w-4 text-gray-500" />
                            <span class="text-gray-700 dark:text-gray-300">{{ __('Updated') }}: {{ $music->updated_at->translatedFormat('Y-m-d') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Actions (only for authenticated users) -->
        @auth
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            @can('update', $music)
                <flux:button variant="primary" icon="pencil" :href="route('music-editor', $music)">
                    {{ __('Edit Music Piece') }}
                </flux:button>
            @endcan
            <flux:button variant="outline" color="zinc" icon="arrow-left" href="{{ route('music-plans') }}">
                {{ __('Back to Music Plans') }}
            </flux:button>
        </div>
        @endauth
    </div>

<livewire:error-report />
</div>