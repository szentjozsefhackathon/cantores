{{-- Shared music table used by both the Musics editor page and the Music Search component.
     Required variables:
       $musics – paginated Music collection
       $mode   – 'manage' | 'select'
     In 'manage' mode the including component must expose: selectedMusicIds, toggleSelection(),
       showAuditLog(), delete().
     In 'select' mode the including component must expose: selectable, selectMusic(). --}}
<flux:table :paginate="$musics">
    <flux:table.columns>
        @if ($mode === 'manage')
            <flux:table.column></flux:table.column>
        @endif
        <flux:table.column>{{ __('Title') }}</flux:table.column>
        <flux:table.column>{{ __('Collection') }}</flux:table.column>
        <flux:table.column>{{ __('Genre') }}</flux:table.column>
        <flux:table.column>{{ __('Tags') }}</flux:table.column>
        @auth
        <flux:table.column><flux:icon name="globe" class="h-4 w-4" /></flux:table.column>
        @endauth
        <flux:table.column>{{ __('Actions') }}</flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @forelse ($musics as $music)
            <flux:table.row>
                {{-- Checkbox (manage only) --}}
                @if ($mode === 'manage')
                    <flux:table.cell>
                        <flux:checkbox
                            wire:click="toggleSelection({{ $music->id }})"
                            :checked="in_array($music->id, $this->selectedMusicIds)" />
                    </flux:table.cell>
                @endif

                {{-- Title --}}
                <flux:table.cell>
                    <div>
                        <div class="font-medium max-w-80 text-wrap">
                            @if ($music->is_verified)
                                <flux:icon name="check" variant="solid" class="inline h-5 w-5 text-green-500" />
                            @endif
                            {{ $music->title }}
                        </div>
                        @if ($music->subtitle)
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ $music->subtitle }}</div>
                        @endif
                        @if ($music->authors->isNotEmpty())
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $music->authors->pluck('name')->join(', ') }}
                            </div>
                        @endif
                        @if ($music->custom_id)
                            <div class="font-mono text-xs text-gray-400 dark:text-gray-500">
                                {{ $music->custom_id }}
                            </div>
                        @endif
                    </div>
                </flux:table.cell>

                {{-- Collections --}}
                <flux:table.cell>
                    <div class="flex flex-wrap items-center gap-2">
                        @forelse ($music->collections as $collection)
                            <flux:badge size="sm">
                                {{ $collection->formatWithPivot($collection->pivot) }}
                            </flux:badge>
                        @empty
                            <span class="text-gray-400 dark:text-gray-500 text-sm">{{ __('None') }}</span>
                        @endforelse
                    </div>
                </flux:table.cell>

                {{-- Genres --}}
                <flux:table.cell>
                    <div class="flex items-center gap-2">
                        @forelse ($music->genres as $genre)
                            <flux:icon
                                name="{{ $genre->icon() }}"
                                class="h-5 w-5 text-gray-600 dark:text-gray-400"
                                :title="$genre->label()" />
                        @empty
                        @endforelse
                    </div>
                </flux:table.cell>

                {{-- Tags --}}
                <flux:table.cell>
                    <div class="flex flex-wrap items-center gap-2">
                        @forelse ($music->tags as $tag)
                            <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                <flux:icon :name="$tag->icon()" class="h-3 w-3" />
                                <span>{{ $tag->name }}</span>
                            </div>
                        @empty
                        @endforelse
                    </div>
                </flux:table.cell>

                @auth
                {{-- Privacy --}}
                <flux:table.cell>
                    <div class="flex items-center gap-2">
                        @if ($music->is_private)
                            <flux:icon name="globe-lock" class="h-5 w-5 text-gray-500 dark:text-gray-400" :title="__('Private')" />
                        @else
                            <flux:icon name="globe" class="h-5 w-5 text-gray-500 dark:text-gray-400" :title="__('Public')" />
                        @endif
                    </div>
                </flux:table.cell>
                @endauth

                {{-- Actions column --}}
                <flux:table.cell>
                    @if ($mode === 'manage')
                        <div class="flex items-center gap-2">
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
                            @else
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="eye"
                                    :href="route('music-view', ['music' => $music->id])"
                                    tag="a"
                                    :title="__('View')" />
                            @endauth
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="history"
                                wire:click="showAuditLog({{ $music->id }})"
                                :title="__('View Audit Log')" />
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
                            variant="ghost"
                            size="sm"
                            icon="plus"
                            wire:click="selectMusic({{ $music->id }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            :title="__('Select')" />
                    @endif
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell :colspan="($mode === 'manage' ? 1 : 0) + 5 + (auth()->check() ? 1 : 0)" class="text-center">
                    <div class="py-8 text-center">
                        <flux:icon name="folder-open" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No music pieces found') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Get started by creating a new music piece.') }}</p>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>
