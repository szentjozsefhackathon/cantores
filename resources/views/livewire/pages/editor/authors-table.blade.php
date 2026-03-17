<div class="space-y-6">
    <!-- Search and Actions -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:field class="w-full sm:w-auto sm:flex-1">
            <flux:input
                type="search"
                wire:model.live.debounce.500ms="search"
                :placeholder="__('Search authors...')"
            />
        </flux:field>
    </div>

    <!-- Authors Table -->
    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Music Pieces') }}</flux:table.column>
            @auth
            <flux:table.column>{{ __('Privacy') }}</flux:table.column>
            @endauth
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($authors as $author)
                <flux:table.row>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            @if($author->avatarThumbUrl())
                                <img src="{{ $author->avatarThumbUrl() }}" alt="{{ $author->name }}"
                                     class="w-8 h-8 rounded-lg object-cover shrink-0" />
                            @else
                                <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                                    <flux:icon name="user" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                </div>
                            @endif
                            <div class="font-medium flex items-center gap-1">
                                {{ $author->name }}
                                @if ($author->is_verified)
                                    @svg('heroicon-s-check', 'inline h-3 w-3 text-green-500 shrink-0')
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                {{ $author->music_count ?? 0 }}
                            </span>
                        </div>
                    </flux:table.cell>

                    @auth
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            @if ($author->is_private)
                                <flux:icon name="globe-lock" class="h-5 w-5 text-gray-500 dark:text-gray-400" :title="__('Private')" />
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Private') }}</span>
                            @else
                                <flux:icon name="globe" class="h-5 w-5 text-gray-500 dark:text-gray-400" :title="__('Public')" />
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Public') }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    @endauth

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                        @auth
                        @can('update', $author)
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil"
                                x-on:click="$dispatch('edit-author', { authorId: {{ $author->id }} })"
                                :title="__('Edit')"
                            />
                        @endcan
                        @else
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="eye"
                                :href="route('author-view', ['author' => $author->id])"
                                tag="a"
                                :title="__('View')"
                            />
                        @endauth
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="history"
                                x-on:click="$dispatch('show-author-audit-log', { authorId: {{ $author->id }} })"
                                :title="__('View Audit Log')"
                            />

                            @can('delete', $author)
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                wire:click="delete({{ $author->id }})"
                                wire:confirm="{{ __('Are you sure you want to delete this author? This can only be done if no music pieces are assigned to it.') }}"
                                :title="__('Delete')"
                            />
                            @endcan

                            @can('verify', $author)
                            <flux:button
                                variant="ghost"
                                size="sm"
                                :icon="$author->is_verified ? 'shield-check' : 'shield-exclamation'"
                                wire:click="verify({{ $author->id }})"
                                wire:confirm="{{ $author->is_verified ? __('Remove verification from this author?') : __('Mark this author as verified?') }}"
                                :title="$author->is_verified ? __('Verified — click to unverify') : __('Unverified — click to verify')"
                            />
                            @endcan

                            @auth
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="flag"
                                wire:click="dispatch('openErrorReportModal', { resourceId: {{ $author->id }}, resourceType: 'author' })"
                                :title="__('Report Error')"
                            />
                            @endauth
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center">
                        <div class="py-8 text-center">
                            <flux:icon name="folder-open" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No authors found') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Get started by creating a new author.') }}</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Pagination -->
    @if ($authors->hasPages())
        <div class="mt-4">
            {{ $authors->links() }}
        </div>
    @endif
</div>
