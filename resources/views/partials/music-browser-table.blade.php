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
        <flux:table.column><svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /><path d="M2 12h20" /></svg></flux:table.column>
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
                                @svg('heroicon-s-check', 'inline h-5 w-5 text-green-500')
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
                            <span class="inline-flex items-center font-medium whitespace-nowrap text-xs py-1 rounded-md px-2 text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40">{{ $collection->formatWithPivot($collection->pivot) }}</span>
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
                            <svg title="{{ __('Private') }}" class="h-5 w-5 text-gray-500 dark:text-gray-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.686 15A14.5 14.5 0 0 1 12 22a14.5 14.5 0 0 1 0-20 10 10 0 1 0 9.542 13" /><path d="M2 12h8.5" /><path d="M20 6V4a2 2 0 1 0-4 0v2" /><rect width="8" height="5" x="14" y="6" rx="1" /></svg>
                        @else
                            <svg title="{{ __('Public') }}" class="h-5 w-5 text-gray-500 dark:text-gray-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /><path d="M2 12h20" /></svg>
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
                                x-on:click="$dispatch('show-music-audit-log', { musicId: {{ $music->id }} })"
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
                            @auth
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="flag"
                                wire:click="dispatch('openErrorReportModal', { resourceId: {{ $music->id }}, resourceType: 'music' })"
                                :title="__('Report Error')" />
                            @endauth
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
                        @svg('heroicon-o-folder-open', 'mx-auto h-12 w-12 text-gray-400 dark:text-gray-500')
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No music pieces found') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Get started by creating a new music piece.') }}</p>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>
