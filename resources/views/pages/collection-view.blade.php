<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <flux:card class="p-5">
            <div class="flex items-start gap-4 mb-6">
                @if($collection->coverUrl())
                    <img src="{{ $collection->coverUrl() }}" alt="{{ $collection->title }}"
                         class="w-20 h-20 shrink-0 rounded-xl object-cover shadow-sm" />
                @else
                    <div class="w-20 h-20 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                        <flux:icon name="book-open" class="w-10 h-10 text-gray-400 dark:text-gray-500" />
                    </div>
                @endif
                <div>
                    <flux:heading size="xl">{{ $collection->title }}</flux:heading>
                    <flux:subheading>
                        @if($collection->abbreviation && $collection->author)
                            {{ $collection->abbreviation }} &middot; {{ $collection->author }}
                        @elseif($collection->abbreviation)
                            {{ $collection->abbreviation }}
                        @elseif($collection->author)
                            {{ $collection->author }}
                        @endif
                    </flux:subheading>
                    @auth
                    <flux:button variant="ghost" icon="flag" wire:click="dispatch('openErrorReportModal', { resourceId: {{ $collection->id }}, resourceType: 'collection' })">
                        {{ __('Report an Issue') }}
                    </flux:button>
                    @endauth
                </div>
            </div>

            <div class="space-y-6">
                <!-- Description -->
                @if($collection->description)
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Description') }}</flux:heading>
                    <flux:text class="text-gray-700 dark:text-gray-300">{{ $collection->description }}</flux:text>
                </div>
                @endif

                <!-- Music pieces in this collection -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">{{ __('Music Pieces in this Collection') }}</flux:heading>
                        <flux:badge color="blue" size="lg">{{ $collection->music()->count() }}</flux:badge>
                    </div>

                    <!-- Search input -->
                    <div class="mb-6">
                        <flux:field>
                            <flux:input
                                type="search"
                                wire:model.live="search"
                                :placeholder="__('Search by title, subtitle, custom ID, or author...')"
                            />
                        </flux:field>
                    </div>
                    
                    @if($musics->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
                            @foreach($musics as $music)
                                <x-music.card :music="$music" wire:key="music-card-{{ $music->id }}" />
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="mt-8">
                            {{ $musics->links() }}
                        </div>
                    @else
                        <flux:callout variant="secondary" icon="musical-note">
                            @if($search)
                                {{ __('No music pieces found matching your search.') }}
                            @else
                                {{ __('No music pieces are assigned to this collection yet.') }}
                            @endif
                        </flux:callout>
                    @endif
                </div>

            </div>

            <!-- Status bar -->
            <div class="mt-6 pt-3 border-t border-neutral-200 dark:border-neutral-700 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                <flux:badge color="{{ $collection->is_private ? 'zinc' : 'green' }}" size="sm">
                    {{ $collection->is_private ? __('Private') : __('Public') }}
                </flux:badge>
                <span class="font-mono">#{{ $collection->id }}</span>
                <span>{{ __('Created by') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->user?->display_name ?? '–' }}</span></span>
                <span>{{ __('Created') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->created_at->translatedFormat('Y-m-d') }}</span></span>
                <span>{{ __('Updated') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->updated_at->translatedFormat('Y-m-d') }}</span></span>
                @if($collection->photo_license)
                    <span>{{ __('Cover license') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->photo_license }}</span></span>
                @endif
            </div>
        </flux:card>

        <!-- Actions (only for authenticated users) -->
        @auth
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            @can('update', $collection)
                <flux:button variant="primary" icon="pencil" wire:click="$dispatch('edit-collection', { collectionId: {{ $collection->id }} })">
                    {{ __('Edit Collection') }}
                </flux:button>
            @endcan

            @can('verify', $collection)
                <flux:button icon="check-badge" wire:click="verify">
                    {{ $collection->is_verified ? __('Un-verify') : __('Verify') }}
                </flux:button>
            @endcan

            <flux:button variant="ghost" icon="logs" wire:click="showAuditLog">
                {{ __('Audit Log') }}
            </flux:button>

            @can('delete', $collection)
                <flux:button variant="danger" icon="trash"
                    wire:click="delete"
                    wire:confirm="{{ __('Are you sure you want to delete this collection? This can only be done if no music pieces are assigned to it.') }}">
                    {{ __('Delete Collection') }}
                </flux:button>
            @endcan
        </div>
        @endauth
    </div>

<flux:modal name="audit-collection" max-width="4xl">
    <flux:heading size="lg">{{ __('Audit Log') }}</flux:heading>
    <flux:subheading>
        {{ __('Collection:') }} {{ $collection->title }}
    </flux:subheading>

    <div class="mt-6">
        @if(count($audits))
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Event') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Changes') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('When') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Who') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($audits as $audit)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    @switch($audit->event)
                                        @case('created')
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">
                                                {{ __('Created') }}
                                            </span>
                                            @break
                                        @case('updated')
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                {{ __('Updated') }}
                                            </span>
                                            @break
                                        @case('deleted')
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300">
                                                {{ __('Deleted') }}
                                            </span>
                                            @break
                                        @default
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                                {{ $audit->event }}
                                            </span>
                                    @endswitch
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    @if($audit->event === 'created')
                                        {{ __('Collection was created.') }}
                                    @elseif($audit->event === 'deleted')
                                        {{ __('Collection was deleted.') }}
                                    @else
                                        @php
                                            $oldValues = $audit->old_values ?? [];
                                            $newValues = $audit->new_values ?? [];
                                            $changes = [];
                                            foreach ($newValues as $key => $value) {
                                                $old = $oldValues[$key] ?? null;
                                                if ($old != $value) {
                                                    $changes[] = __($key) . ': "' . ($old ?? __('empty')) . '" → "' . ($value ?? __('empty')) . '"';
                                                }
                                            }
                                        @endphp
                                        @if(count($changes))
                                            <ul class="list-disc list-inside space-y-1">
                                                @foreach($changes as $change)
                                                    <li class="text-xs">{{ $change }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">{{ __('No field changes recorded') }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $audit->created_at->translatedFormat('Y-m-d H:i:s') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if($audit->user)
                                        {{ $audit->user->display_name }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">{{ __('System') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <flux:icon name="logs" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No audit logs found') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('No changes have been recorded for this collection yet.') }}</p>
            </div>
        @endif
    </div>

    <div class="mt-6 flex justify-end">
        <flux:modal.close>
            <flux:button variant="ghost">{{ __('Close') }}</flux:button>
        </flux:modal.close>
    </div>
</flux:modal>

<livewire:pages.editor.collection-edit-modal />

<livewire:error-report />
</div>