<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

    <div class="mb-8">
        <flux:heading size="2xl">{{ __('Authors') }}</flux:heading>
        <flux:subheading>{{ __('Manage music authors') }}</flux:subheading>
    </div>

    <!-- Action messages -->
    <div class="mb-4 flex justify-end">
        <x-action-message on="author-created">
            {{ __('Author created.') }}
        </x-action-message>
        <x-action-message on="author-updated">
            {{ __('Author updated.') }}
        </x-action-message>
        <x-action-message on="author-deleted">
            {{ __('Author deleted.') }}
        </x-action-message>
        <x-action-message on="error" />
        <x-action-message on="success" />
    </div>

    <div class="space-y-6">
        <!-- Search and Actions -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:field class="w-full sm:w-auto sm:flex-1">
                <flux:input
                    type="search"
                    wire:model.live="search"
                    :placeholder="__('Search authors...')"
                />
            </flux:field>
            
            <flux:button
                variant="primary"
                icon="plus"
                wire:click="create"
            >
                {{ __('Create Author') }}
            </flux:button>
        </div>

        <!-- Authors Table -->
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Music Pieces') }}</flux:table.column>
                <flux:table.column>{{ __('Privacy') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse ($authors as $author)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $author->name }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $author->music_count ?? 0 }}
                                </span>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if ($author->is_private)
                                    <flux:icon name="eye-slash" class="h-5 w-5 text-gray-500 dark:text-gray-400" :title="__('Private')" />
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Private') }}</span>
                                @else
                                    <flux:icon name="globe" class="h-5 w-5 text-gray-500 dark:text-gray-400" :title="__('Public')" />
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Public') }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                            @can('update', $author)                                
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil"
                                    wire:click="edit({{ $author->id }})"
                                    :title="__('Edit')"
                                />
                            @endcan
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="history"
                                    wire:click="showAuditLog({{ $author->id }})"
                                    :title="__('View Audit Log')"
                                />
                                
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="delete({{ $author->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this author? This can only be done if no music pieces are assigned to it.') }}"
                                    :title="__('Delete')"
                                />
                                
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="flag"
                                    wire:click="dispatch('openErrorReportModal', { resourceId: {{ $author->id }}, resourceType: 'author' })"
                                    :title="__('Report Error')"
                                />
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

    @if($showCreateModal)
    <!-- Modals outside main content for single root -->
    <flux:modal wire:model="showCreateModal" max-width="md">
        <flux:heading size="lg">{{ __('Create Author') }}</flux:heading>

        <div class="mt-2 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input
                    wire:model="name"
                    :placeholder="__('Enter author name')"
                    autofocus
                />
                <flux:error name="name" />
            </flux:field>
            
            <flux:field>
                <flux:checkbox
                    wire:model="isPrivate"
                    :label="__('Make this author private (only visible to you)')"
                />
                <flux:description>{{ __('Private authors are only visible to you and cannot be seen by other users.') }}</flux:description>
            </flux:field>

        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="$set('showCreateModal', false)"
            >
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="store"
            >
                {{ __('Create') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    @if($showAuditModal)
    <flux:modal wire:model="showAuditModal" max-width="4xl">
        <flux:heading size="lg">{{ __('Audit Log') }}</flux:heading>
        <flux:subheading>
            {{ __('Author:') }} {{ $auditingAuthor->name ?? '' }}
        </flux:subheading>

        <div class="mt-6">
            @if($auditingAuthor && count($audits))
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
                                            {{ __('Author was created.') }}
                                        @elseif($audit->event === 'deleted')
                                            {{ __('Author was deleted.') }}
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
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('No changes have been recorded for this author yet.') }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6 flex justify-end">
            <flux:button
                variant="ghost"
                wire:click="$set('showAuditModal', false)"
            >
                {{ __('Close') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    @if($showEditModal)
    <flux:modal wire:model="showEditModal" max-width="md">
        <flux:heading size="lg">{{ __('Edit Author') }}</flux:heading>

        <div class="mt-2 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input
                    wire:model="name"
                    :placeholder="__('Enter author name')"
                />
                <flux:error name="name" />
            </flux:field>

            @can('changePrivacy', $editingAuthor)
            <flux:field>
                <flux:checkbox
                    wire:model="isPrivate"
                    :label="__('Make this author private (only visible to you)')"
                />
                <flux:description>{{ __('Private authors are only visible to you and cannot be seen by other users.') }}</flux:description>
            </flux:field>
            @endcan

        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="$set('showEditModal', false)"
            >
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="update"
            >
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    <livewire:error-report />
</div>
