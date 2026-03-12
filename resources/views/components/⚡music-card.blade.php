<?php

use App\Models\Music;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public Music $music;

    public function mount(Music $music): void
    {
        $this->music = $music->load(['collections', 'tags', 'authors', 'urls', 'directMusicRelations.relatedMusic', 'inverseMusicRelations.music']);
    }

    #[On('music-updated')]
    #[On('collection-added')]
    #[On('collection-removed')]
    #[On('collection-updated')]
    #[On('tag-added')]
    #[On('tag-removed')]
    public function refreshMusic(): void
    {
        $this->music->refresh()->load(['collections', 'tags', 'authors', 'urls', 'directMusicRelations.relatedMusic', 'inverseMusicRelations.music']);
    }
}
?>

@placeholder
<flux:skeleton.group animate="shimmer" class="flex items-center gap-4">
    <flux:skeleton class="size-10 rounded-full" />
    <div class="flex-1">
        <flux:skeleton.line />
        <flux:skeleton.line class="w-1/2" />
    </div>
</flux:skeleton.group>
@endplaceholder


<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden max-w-[355px] relative group transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-2xl"
     style="box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08), 0 4px 8px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.12);"
>
    @can('view', $music)
    <a href="{{ route('music-view', $music) }}" class="absolute inset-0 z-0" aria-label="{{ $music->title }}"></a>
    @endcan
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
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                     {{ $music->title }}
                </h3>
                @if($music->subtitle)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $music->subtitle }}
                    </p>
                @endif

                    <div class="mt-1 flex flex-wrap gap-1">
                        @if($music->custom_id)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                            {{ $music->custom_id }}
                        </span>
                        @endif
                        @foreach($music->collections as $collection)
                            <x-collection-badge :collection="$collection" />
                        @endforeach
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
                        target="_blank"
                        :title="__('Edit')"
                        class="!p-1"
                    />
                @endcan
                </div>
            </div>
        </div>
    </div>

    @if($music->authors->isNotEmpty() || $music->urls->isNotEmpty() || $music->allMusicRelations()->isNotEmpty())
    <div class="px-4 py-3 space-y-2">
        @if($music->authors->isNotEmpty())
        <div class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
            <flux:icon name="users" class="size-4 shrink-0 mt-0.5 text-gray-400 dark:text-gray-500" />
            <div class="flex flex-wrap gap-1">
                @foreach($music->authors as $author)
                    <span>{{ $author->name }}@unless($loop->last),@endunless</span>
                @endforeach
            </div>
        </div>
        @endif

        @if($music->urls->isNotEmpty())
        <div class="flex items-start gap-2">
            <flux:icon name="arrow-top-right-on-square" class="size-4 shrink-0 mt-0.5 text-gray-400 dark:text-gray-500" />
            <div class="flex flex-wrap gap-2">
                @foreach($music->urls as $url)
                    <a href="{{ $url->url }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="relative z-10 text-xs text-blue-600 dark:text-blue-400 hover:underline"
                       title="{{ $url->label ? (\App\MusicUrlLabel::tryFrom($url->label)?->label() ?? $url->label) : $url->url }}"
                    >{{ $url->label ? (\App\MusicUrlLabel::tryFrom($url->label)?->label() ?? $url->label) : $url->url }}</a>
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
                    <a href="{{ route('music-view', $partner) }}"
                       class="relative z-10 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    >
                        {{ $partner->title }}
                        @if($relation->relationship_type)
                            <span class="text-gray-400">({{ $relation->relationship_type }})</span>
                        @endif
                    </a>
                    @endcan
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif
</div>
