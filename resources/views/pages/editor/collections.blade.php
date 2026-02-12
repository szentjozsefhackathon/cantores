<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8">
            <flux:heading size="2xl">{{ __('Collections') }}</flux:heading>
            <flux:subheading>{{ __('Manage music collections') }}</flux:subheading>
        </div>

        <div class="space-y-6">
            <!-- Search and Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <flux:field class="w-full sm:w-auto sm:flex-1">
                    <flux:input
                        type="search"
                        wire:model.live="search"
                        :placeholder="__('Search collections by title, abbreviation, or author...')"
                    />
                </flux:field>
                
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:click="create"
                >
                    {{ __('Create Collection') }}
                </flux:button>
            </div>

        <!-- Collections Table -->
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Abbreviation') }}</flux:table.column>
                <flux:table.column>{{ __('Author') }}</flux:table.column>
                <flux:table.column>{{ __('Music Pieces') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse ($collections as $collection)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $collection->title }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            @if ($collection->abbreviation)
                                <div class="font-mono text-sm text-gray-600 dark:text-gray-400">
                                    {{ $collection->abbreviation }}
                                </div>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('None') }}</span>
                            @endif
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            @if ($collection->author)
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $collection->author }}
                                </div>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Unknown') }}</span>
                            @endif
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $collection->music_count ?? 0 }}
                                </span>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    icon="pencil" 
                                    wire:click="edit({{ $collection->id }})"
                                    :title="__('Edit')"
                                />
                                
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    icon="trash" 
                                    wire:click="delete({{ $collection->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this collection? This can only be done if no music pieces are assigned to it.') }}"
                                    :title="__('Delete')"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">
                            <div class="py-8 text-center">
                                <flux:icon name="folder-open" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No collections found') }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Get started by creating a new collection.') }}</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if ($collections->hasPages())
            <div class="mt-4">
                {{ $collections->links() }}
            </div>
        @endif
    </div>

    <!-- Modals outside main content for single root -->
    <flux:modal wire:model="showCreateModal" max-width="md">
        <flux:heading size="lg">{{ __('Create Collection') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field :label="__('Title')" required>
                <flux:input
                    wire:model="title"
                    :placeholder="__('Enter collection title')"
                />
                <flux:error name="title" />
            </flux:field>

            <flux:field :label="__('Abbreviation')" :helper="__('Optional short form, e.g., ÉE, BWV')">
                <flux:input
                    wire:model="abbreviation"
                    :placeholder="__('Enter abbreviation')"
                    maxlength="20"
                />
                <flux:error name="abbreviation" />
            </flux:field>

            <flux:field :label="__('Author')" :helper="__('Optional author or publisher')">
                <flux:input
                    wire:model="author"
                    :placeholder="__('Enter author name')"
                />
                <flux:error name="author" />
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

    <flux:modal wire:model="showEditModal" max-width="md">
        <flux:heading size="lg">{{ __('Edit Collection') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field :label="__('Title')" required>
                <flux:input
                    wire:model="title"
                    :placeholder="__('Enter collection title')"
                />
                <flux:error name="title" />
            </flux:field>

            <flux:field :label="__('Abbreviation')" :helper="__('Optional short form, e.g., ÉE, BWV')">
                <flux:input
                    wire:model="abbreviation"
                    :placeholder="__('Enter abbreviation')"
                    maxlength="20"
                />
                <flux:error name="abbreviation" />
            </flux:field>

            <flux:field :label="__('Author')" :helper="__('Optional author or publisher')">
                <flux:input
                    wire:model="author"
                    :placeholder="__('Enter author name')"
                />
                <flux:error name="author" />
            </flux:field>
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