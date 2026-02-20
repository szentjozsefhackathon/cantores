<?php

use App\Models\Music;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public Music $music;

    public function mount(Music $music): void
    {
        $this->music = $music->load([
            'collections',
            'authors',
            'genres',
            'urls',
            'relatedMusic.authors',
            'relatedMusic.collections',
        ]);
    }

    #[On('music-updated')]
    #[On('collection-added')]
    #[On('collection-removed')]
    #[On('collection-updated')]
    public function refreshMusic(): void
    {
        $this->music->refresh()->load([
            'collections',
            'authors',
            'genres',
            'urls',
            'relatedMusic.authors',
            'relatedMusic.collections',
        ]);
    }
}
?>

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden max-w-[500px]">
    <!-- Header with title and custom ID -->
    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 ">
                     {{ $music->title }}
                </h3>
                @if($music->subtitle)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">
                        {{ $music->subtitle }}
                    </p>
                @endif

                    <div class="mt-0.5">
                        @if($music->custom_id)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            {{ $music->custom_id }}
                        </span>
                        @endif
                        @foreach($music->collections as $collection)
                            <livewire:collection-badge :collection="$collection" />
                        @endforeach
                    </div>
            </div>
            <div class="flex items-center gap-1">
                <div class="flex-col items-center gap-1">
                @can('view', $music)
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="eye"
                        :href="route('music-view', $music)"
                        :title="__('View')"
                        class="!p-1"
                    />
                @endcan
                @can('update', $music)
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="pencil"
                        :href="route('music-editor', $music)"
                        target="_blank"
                        :title="__('Edit')"
                        class="!p-1"
                    />
                @endcan
                <div class="hidden sm:flex flex-col items-center gap-1">
                @foreach($music->genres as $genre)
                    <flux:icon name="{{ $genre->icon() }}" class="h-5 w-5 flex-shrink-0 text-zinc-600 dark:text-zinc-300" />
                @endforeach
                </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Additional details -->
    <div class="p-3 space-y-3">
        <!-- Authors -->
        @if($music->authors->isNotEmpty())
        <div>
            <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Authors') }}</flux:heading>
            <div class="flex flex-wrap gap-1">
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

        <!-- Genres (text) -->
        @if($music->genres->isNotEmpty())
        <div>
            <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Genres') }}</flux:heading>
            <div class="flex flex-wrap gap-1">
                @foreach($music->genres as $genre)
                    <flux:badge color="blue" size="sm" :icon="$genre->icon()">
                        {{ $genre->label() }}
                    </flux:badge>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Related Music -->
        @if($music->relatedMusic->isNotEmpty())
        <div>
            <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Related Music') }}</flux:heading>
            <div class="space-y-2">
                @foreach($music->relatedMusic as $related)
                    <div class="flex flex-col p-1.5 border border-gray-200 dark:border-gray-700 rounded-md bg-gray-50 dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('music-view', $related) }}" class="font-medium text-sm text-gray-900 dark:text-gray-100 hover:underline">
                                    {{ $related->title }}
                                </a>
                                @if($related->subtitle)
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $related->subtitle }}</div>
                                @endif
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    @foreach($related->authors as $author)
                                        <flux:badge color="gray" size="xs">{{ $author->name }}</flux:badge>
                                    @endforeach
                                    @foreach($related->collections as $collection)
                                        <flux:badge size="xs" color="zinc">
                                            {{ $collection->abbreviation ?? $collection->title }} {{ $collection->pivot->order_number }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            </div>
                            <div class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ \App\MusicRelationshipType::from($related->pivot->relationship_type)->name }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- URLs -->
        @if($music->urls->isNotEmpty())
        <div>
            <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('External Links') }}</flux:heading>
            <div class="grid grid-cols-1 gap-2">
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
                        <div class="flex items-center gap-2 p-1.5 border border-gray-200 dark:border-gray-700 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <flux:icon :name="$icon" class="h-4 w-4 text-{{ $color }}-500" />
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $labelText }}</span>
                            <flux:icon name="external-link" class="h-3 w-3 text-gray-400 ml-auto" />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>