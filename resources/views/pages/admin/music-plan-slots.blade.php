<x-pages::admin.layout :heading="__('Music Plan Slots')" :subheading="__('Manage global music plan slots that can be used across templates')">
    <div class="space-y-6">
        <!-- Action messages -->
        <div class="flex justify-end">
            <x-action-message on="slot-created">
                {{ __('Slot created.') }}
            </x-action-message>
            <x-action-message on="slot-updated">
                {{ __('Slot updated.') }}
            </x-action-message>
            <x-action-message on="slot-deleted">
                {{ __('Slot deleted.') }}
            </x-action-message>
        </div>

        <!-- Search and Actions -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:field class="w-full sm:w-auto sm:flex-1">
                <flux:input 
                    type="search" 
                    wire:model.live="search" 
                    :placeholder="__('Search slots by name or description...')" 
                />
            </flux:field>
            
            <flux:button 
                variant="primary" 
                icon="plus" 
                wire:click="showCreate"
            >
                {{ __('Create Slot') }}
            </flux:button>
        </div>

        <!-- Slots Table -->
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Priority') }}</flux:table.column>
                <flux:table.column>{{ __('Used in Templates') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse ($musicPlanSlots as $musicPlanSlot)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $musicPlanSlot->name }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            @if ($musicPlanSlot->description)
                                <div class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                    {{ $musicPlanSlot->description }}
                                </div>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('No description') }}</span>
                            @endif
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="font-medium">{{ $musicPlanSlot->priority }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-300">
                                {{ $musicPlanSlot->templates_count ?? 0 }}
                            </span>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    icon="pencil" 
                                    wire:click="showEdit({{ $musicPlanSlot->id }})"
                                    :title="__('Edit')"
                                />
                                
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    icon="trash" 
                                    wire:click="delete({{ $musicPlanSlot->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this slot? This will remove it from all templates.') }}"
                                    :title="__('Delete')"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8">
                            <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                <flux:icon name="musical-note" class="h-12 w-12 mb-2 opacity-50" />
                                <p class="text-lg font-medium">{{ __('No slots found') }}</p>
                                <p class="text-sm mt-1">
                                    @if ($search)
                                        {{ __('Try a different search term') }}
                                    @else
                                        {{ __('Create your first music plan slot') }}
                                    @endif
                                </p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if ($musicPlanSlots->hasPages())
            <div class="mt-4">
                {{ $musicPlanSlots->links() }}
            </div>
        @endif
    </div>

    <!-- Create Modal -->
    <flux:modal wire:model="showCreateModal">
        <flux:heading>{{ __('Create Music Plan Slot') }}</flux:heading>
        
        <form wire:submit="create" class="space-y-4">
            <flux:field>
                <flux:label for="create-name">{{ __('Name') }} *</flux:label>
                <flux:input
                    id="create-name"
                    wire:model="name"
                    :placeholder="__('e.g., Entrance Procession, Kyrie, Gloria')"
                    required
                />
                <flux:error name="name" />
            </flux:field>
            
            <flux:field>
                <flux:label for="create-description">{{ __('Description') }}</flux:label>
                <flux:textarea
                    id="create-description"
                    wire:model="description"
                    :placeholder="__('Optional description of this slot')"
                    rows="3"
                />
                <flux:error name="description" />
            </flux:field>
            
            <flux:field>
                <flux:label for="create-priority">{{ __('Priority') }} *</flux:label>
                <flux:input
                    id="create-priority"
                    type="number"
                    wire:model="priority"
                    :placeholder="__('0')"
                    min="0"
                    required
                />
                <flux:description>{{ __('Lower numbers have higher priority (e.g., 1 appears before 2). Defines order in unified celebration view.') }}</flux:description>
                <flux:error name="priority" />
            </flux:field>
            
            <div class="flex justify-end gap-3 pt-4">
                <flux:button 
                    variant="ghost" 
                    wire:click="$set('showCreateModal', false)"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button 
                    type="submit" 
                    variant="primary"
                >
                    {{ __('Create Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Modal -->
    <flux:modal wire:model="showEditModal">
        <flux:heading>{{ __('Edit Music Plan Slot') }}</flux:heading>
        
        <form wire:submit="update" class="space-y-4">
            <flux:field>
                <flux:label for="edit-name">{{ __('Name') }} *</flux:label>
                <flux:input 
                    id="edit-name" 
                    wire:model="name" 
                    required
                />
                    <flux:error name="name" />
            </flux:field>
            
            <flux:field>
                <flux:label for="edit-description">{{ __('Description') }}</flux:label>
                <flux:textarea
                    id="edit-description"
                    wire:model="description"
                    rows="3"
                />
                    <flux:error name="description" />
            </flux:field>
            
            <flux:field>
                <flux:label for="edit-priority">{{ __('Priority') }} *</flux:label>
                <flux:input
                    id="edit-priority"
                    type="number"
                    wire:model="priority"
                    :placeholder="__('0')"
                    min="0"
                    required
                />
                <flux:description>{{ __('Lower numbers have higher priority (e.g., 1 appears before 2). Defines order in unified celebration view.') }}</flux:description>
                <flux:error name="priority" />
            </flux:field>
            
            <div class="flex justify-end gap-3 pt-4">
                <flux:button 
                    variant="ghost" 
                    wire:click="$set('showEditModal', false)"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button 
                    type="submit" 
                    variant="primary"
                >
                    {{ __('Update Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</x-pages::admin.layout>