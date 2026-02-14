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
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                                {{ $collection->abbreviation }} {{ $collection->pivot->order_number }}
                            </span>
                        @endforeach
                    </div>
            </div>
            <flux:icon name="music" class="h-5 w-5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
        </div>
    </div>
</div>