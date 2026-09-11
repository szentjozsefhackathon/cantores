{{-- Shared music table used by both the Musics editor page and the Music Search component.
     Required variables:
       $musics – paginated Music collection
       $mode   – 'manage' | 'select'
     In 'manage' mode the including component must expose: selectedMusicIds, toggleSelection(),
       delete().
     In 'select' mode the including component must expose: selectable, selectMusic(). --}}
<flux:table :paginate="$musics" class="w-full [&_td]:whitespace-normal [&_td]:wrap-anywhere">
    <flux:table.columns>
        @if ($mode === 'manage')
            @can('mergeAny', \App\Models\Music::class)
                <flux:table.column class="hidden sm:table-cell w-8"></flux:table.column>
            @endcan
        @endif
        <flux:table.column>{{ __('Title') }}</flux:table.column>
        <flux:table.column class="hidden sm:table-cell sm:w-1/5">{{ __('Collection') }}</flux:table.column>
        <flux:table.column class="hidden sm:table-cell w-12"><span class="sr-only">{{ __('Genre') }}</span></flux:table.column>
        <flux:table.column class="hidden sm:table-cell w-1/5">{{ __('Tags') }}</flux:table.column>
        <flux:table.column :class="$mode === 'select' ? ($this->selectable ? 'w-14' : 'w-0 p-0!') : 'w-14 sm:w-28'"></flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @forelse ($musics as $music)
            <flux:table.row>
                {{-- Checkbox (manage + can merge only) --}}
                @if ($mode === 'manage')
                    @can('mergeAny', \App\Models\Music::class)
                        <flux:table.cell class="hidden sm:table-cell w-8">
                            <flux:checkbox
                                wire:click="toggleSelection({{ $music->id }})"
                                :checked="in_array($music->id, $this->selectedMusicIds)" />
                        </flux:table.cell>
                    @endcan
                @endif

                {{-- Title and privacy (+ genre/tags on mobile) --}}
                <flux:table.cell>
                    <div>
                        <div class="font-medium max-w-80 text-wrap wrap-anywhere">
                            @can('view', $music)
                                <a href="{{ route('music-view', $music) }}" class="inline-flex max-w-full items-center gap-0.5 hover:underline group">
                                    <span class="min-w-0">{{ $music->title }}</span>
                                    @if ($music->is_private)
                                        <flux:icon name="lock-closed" class="h-4 w-4 shrink-0" :aria-label="__('Private')" :title="__('Private')" />
                                    @endif
                                    @if ($music->is_verified)
                                        @svg('heroicon-s-check', 'inline h-2.5 w-2.5 text-green-500 shrink-0')
                                    @endif
                                    @svg('heroicon-s-chevron-right', 'h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity')
                                </a>
                            @else
                                <span class="inline-flex max-w-full items-center gap-0.5">
                                    <span class="min-w-0">{{ $music->title }}</span>
                                    @if ($music->is_private)
                                        <flux:icon name="lock-closed" class="h-4 w-4 shrink-0" :aria-label="__('Private')" :title="__('Private')" />
                                    @endif
                                    @if ($music->is_verified)
                                        @svg('heroicon-s-check', 'inline h-2.5 w-2.5 text-green-500 shrink-0')
                                    @endif
                                </span>
                            @endcan
                        </div>
                        @if ($music->subtitle)
                            <div class="text-sm text-wrap wrap-anywhere text-gray-600 dark:text-gray-400">{{ $music->subtitle }}</div>
                        @endif
                        @if ($music->authors->isNotEmpty())
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2 text-wrap wrap-anywhere">
                                {{ $music->authors->take(2)->pluck('name')->join(', ') }}
                                @if ($music->authors->count() > 2)
                                    <span class="whitespace-nowrap">+{{ $music->authors->count() - 2 }}</span>
                                @endif
                            </div>
                        @endif

                        @if ($music->urls->isNotEmpty())
                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                @foreach ($music->urls->unique('label') as $url)
                                    @php
                                        $urlType = \App\MusicUrlLabel::tryFromLabel($url->label);
                                    @endphp
                                    @if ($urlType)
                                        <flux:icon :name="$urlType->icon()" class="h-5 w-5 {{ $urlType->color() }}" :title="$urlType->label()" />
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        @if ($music->custom_id)
                            <div class="font-mono text-xs text-gray-400 dark:text-gray-500">
                                {{ $music->custom_id }}
                            </div>
                        @endif

                        {{-- Genre + tags (mobile only) --}}
                        <div class="mt-1 flex flex-wrap items-center gap-1 sm:hidden">
                            @foreach ($music->genres as $genre)
                                <flux:icon
                                    name="{{ $genre->icon() }}"
                                    class="h-4 w-4 text-gray-500 dark:text-gray-400"
                                    :title="$genre->label()" />
                            @endforeach
                            @foreach ($music->tags as $tag)
                                <div class="inline-flex max-w-full items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                    <flux:icon :name="$tag->icon()" class="h-3 w-3" />
                                    <span class="min-w-0 text-wrap wrap-anywhere">{{ $tag->name }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-2 sm:hidden">
                            @include('partials.music-browser-collections')
                        </div>

                        @php $incipitScore = $music->visibleIncipitScores(auth()->user())->first(); @endphp
                        @if ($incipitScore)
                            <div class="mt-1.5">
                                <x-incipit-image class="max-w-full" :src="$incipitScore->public_preview ? $incipitScore->publicIncipitUrl() : $incipitScore->incipitUrl()"
                                     :alt="$incipitScore->title"
                                     img-class="h-12 w-auto max-w-full object-contain" />
                            </div>
                        @endif
                    </div>
                </flux:table.cell>

                {{-- Collections --}}
                <flux:table.cell class="hidden sm:table-cell">
                    @include('partials.music-browser-collections')
                </flux:table.cell>

                {{-- Genres (desktop only) --}}
                <flux:table.cell class="hidden sm:table-cell">
                    <div class="flex flex-wrap items-center gap-2">
                        @forelse ($music->genres as $genre)
                            <flux:icon
                                name="{{ $genre->icon() }}"
                                class="h-5 w-5 text-gray-600 dark:text-gray-400"
                                :title="$genre->label()" />
                        @empty
                        @endforelse
                    </div>
                </flux:table.cell>

                {{-- Tags (desktop only) --}}
                <flux:table.cell class="hidden sm:table-cell">
                    <div class="flex flex-wrap items-center gap-2">
                        @forelse ($music->tags as $tag)
                            <div class="inline-flex max-w-full items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                <flux:icon :name="$tag->icon()" class="h-3 w-3" />
                                <span class="min-w-0 text-wrap wrap-anywhere">{{ $tag->name }}</span>
                            </div>
                        @empty
                        @endforelse
                    </div>
                </flux:table.cell>

                {{-- Actions column --}}
                <flux:table.cell :class="$mode === 'select' && ! $this->selectable ? 'p-0!' : ''">
                    @if ($mode === 'manage')
                        <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-2">
                            @auth
                                @can('content.edit.own')
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil"
                                        :href="route('music-editor', ['music' => $music->id])"
                                        tag="a"
                                        :title="__('Edit')" />
                                @endcan
                            @endauth
                            @can('content.edit.published')
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="delete({{ $music->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this music piece? This will remove it from all collections and music plans.') }}"
                                    :title="__('Delete')" />
                            @endcan
                        </div>
                    @elseif ($mode === 'select' && $this->selectable)
                        <flux:button
                            variant="primary"
                            color="green"
                            size="sm"
                            icon="plus"
                            wire:click="selectMusic({{ $music->id }})"
                            wire:loading.attr="disabled"
                            :aria-label="__('Add').': '.$music->title"
                            :title="__('Add')" />
                    @endif
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="10" class="text-center">
                    <div class="py-8 text-center">
                        @svg('heroicon-o-folder-open', 'mx-auto h-12 w-12 text-gray-400 dark:text-gray-500')
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No music pieces found') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Get started by creating a new music piece.') }}</p>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>
