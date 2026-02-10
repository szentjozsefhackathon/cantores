<x-pages::admin.layout :heading="__('Music Plan Templates')" :subheading="__('Manage templates for organizing music plans')">
    <div class="space-y-6">
        <!-- Search and Actions -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:field class="w-full sm:w-auto sm:flex-1">
                <flux:input 
                    type="search" 
                    wire:model.live="search" 
                    :placeholder="__('Search templates by name or description...')" 
                />
            </flux:field>
            
            <flux:button 
                variant="primary" 
                icon="plus" 
                wire:click="showCreate"
            >
                {{ __('Create Template') }}
            </flux:button>
        </div>

        <!-- Templates Table -->
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Slots') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse ($templates as $template)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $template->name }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            @if ($template->description)
                                <div class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                    {{ $template->description }}
                                </div>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('No description') }}</span>
                            @endif
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:switch 
                                    wire:model.live="is_active" 
                                    wire:click="toggleActive({{ $template->id }})"
                                    :checked="$template->is_active"
                                    size="sm"
                                />
                                <span class="text-sm {{ $template->is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $template->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $template->slots_count ?? 0 }}
                                </span>
                                @if ($template->slots_count > 0)
                                    <flux:button 
                                        variant="ghost" 
                                        size="sm" 
                                        icon="list-bullet"
                                        :href="route('admin.music-plan-template-slots', ['template' => $template->id])"
                                        wire:navigate
                                        :title="__('Manage slots')"
                                    />
                                @endif
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    icon="list-bullet" 
                                    :href="route('admin.music-plan-template-slots', ['template' => $template->id])"
                                    wire:navigate
                                    :title="__('Manage slots')"
                                />
                                
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    icon="pencil" 
                                    wire:click="showEdit({{ $template->id }})"
                                    :title="__('Edit')"
                                />
                                
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    icon="trash" 
                                    wire:click="delete({{ $template->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this template? This will not affect existing music plans.') }}"
                                    :title="__('Delete')"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8">
                            <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                <x-lucide-form class="h-12 w-12 mb-2 opacity-50" />
                                <p class="text-lg font-medium">{{ __('No templates found') }}</p>
                                <p class="text-sm mt-1">
                                    @if ($search)
                                        {{ __('Try a different search term') }}
                                    @else
                                        {{ __('Create your first music plan template') }}
                                    @endif
                                </p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if ($templates->hasPages())
            <div class="mt-4">
                {{ $templates->links() }}
            </div>
        @endif
    </div>

    <!-- Create Modal -->
    <flux:modal wire:model="showCreateModal">
        <flux:heading>{{ __('Create Music Plan Template') }}</flux:heading>
        
        <form wire:submit="create" class="space-y-4">
            <flux:field>
                <flux:label for="create-name">{{ __('Name') }} *</flux:label>
                <flux:input 
                    id="create-name" 
                    wire:model="name" 
                    :placeholder="__('e.g., Sunday Mass, Wedding, Funeral')" 
                    required
                />
                @error('name')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
            
            <flux:field>
                <flux:label for="create-description">{{ __('Description') }}</flux:label>
                <flux:textarea 
                    id="create-description" 
                    wire:model="description" 
                    :placeholder="__('Optional description of this template')" 
                    rows="3"
                />
                @error('description')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
            
            <flux:field>
                <flux:checkbox 
                    id="create-is-active" 
                    wire:model="is_active"
                >
                    {{ __('Active (available for use)') }}
                </flux:checkbox>
                @error('is_active')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
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
                    {{ __('Create Template') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Modal -->
    <flux:modal wire:model="showEditModal">
        <flux:heading>{{ __('Edit Music Plan Template') }}</flux:heading>
        
        <form wire:submit="update" class="space-y-4">
            <flux:field>
                <flux:label for="edit-name">{{ __('Name') }} *</flux:label>
                <flux:input 
                    id="edit-name" 
                    wire:model="name" 
                    required
                />
                @error('name')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
            
            <flux:field>
                <flux:label for="edit-description">{{ __('Description') }}</flux:label>
                <flux:textarea 
                    id="edit-description" 
                    wire:model="description" 
                    rows="3"
                />
                @error('description')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
            
            <flux:field>
                <flux:checkbox 
                    id="edit-is-active" 
                    wire:model="is_active"
                >
                    {{ __('Active (available for use)') }}
                </flux:checkbox>
                @error('is_active')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
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
                    {{ __('Update Template') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</x-pages::admin.layout>