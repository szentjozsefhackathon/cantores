<?php

use App\Models\Music;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public Music $music;

    public function mount(Music $music): void
    {
        $this->music = $music->load('collections');
    }

    #[On('music-updated')]
    #[On('collection-added')]
    #[On('collection-removed')]
    #[On('collection-updated')]
    public function refreshMusic(): void
    {
        $this->music->refresh()->load('collections');
    }
}
?>

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden max-w-[355px]">
    <!-- Header with title and custom ID -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 ">
                     {{ $music->title }}
                </h3>
                @if($music->subtitle)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $music->subtitle }}
                    </p>
                @endif

                    <div class="mt-1">
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
                    <flux:icon name="{{ $genre->icon() }}" class="h-5 w-5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
                @endforeach
                </div>
                </div>
                
            </div>
        </div>
    </div>
</div>
