<?php

use App\Enums\MusicTagType;
use App\Models\MusicTag;
use Illuminate\Contracts\View\View;

new class extends \Livewire\Component
{
    // Component logic is in MusicTagManager.php
};

?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6">
            <flux:button
                variant="ghost"
                icon="arrow-left"
                :href="route('dashboard')"
                tag="a">
                {{ __('Back to Dashboard') }}
            </flux:button>
        </div>

        <flux:card class="p-5">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <flux:heading size="xl">{{ __('Manage Music Tags') }}</flux:heading>
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Create and manage music tags. Tags are organized by type for easy filtering and querying.') }}
                    </flux:text>
                </div>
            </div>

            <!-- Create New Tag Form -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <flux:heading size="sm">{{ __('Create New Tag') }}</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('Add a new tag with a name and type.') }}
                </flux:text>

                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <flux:field required>
                            <flux:label>{{ __('Tag Name') }}</flux:label>
                            <flux:input
                                wire:model="newTagName"
                                :placeholder="__('e.g., Introitus, Organ, Advent')" />
                            <flux:error name="newTagName" />
                        </flux:field>

                        <flux:field required>
                            <flux:label>{{ __('Tag Type') }}</flux:label>
                            <flux:select wire:model="newTagType">
                                <option value="">{{ __('Select a type') }}</option>
                                @foreach($tagTypes as $type)
                                <flux:select.option value="{{ $type->value }}">
                                    {{ $type->label() }}
                                </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="newTagType" />
                        </flux:field>

                        <div class="flex items-end">
                            <flux:button
                                variant="primary"
                                wire:click="createTag"
                                wire:loading.attr="disabled"
                                class="w-full">
                                {{ __('Create Tag') }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-action-message on="tag-created">
                            {{ __('Tag created successfully.') }}
                        </x-action-message>
                    </div>
                </div>
            </div>

            <!-- Tags List -->
            <div>
                <flux:heading size="sm">{{ __('Existing Tags') }}</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('View and manage all existing tags.') }}
                </flux:text>

                @if($tags->count())
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Tag Name') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Type') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Usage Count') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($tags as $tag)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($editingTagId === $tag->id)
                                        <flux:input
                                            wire:model="editingTagName"
                                            class="w-full" />
                                    @else
                                        <div class="flex items-center gap-2">
                                            <flux:icon :name="$tag->icon()" class="h-4 w-4" />
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $tag->name }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($editingTagId === $tag->id)
                                        <flux:select wire:model="editingTagType" class="w-full">
                                            @foreach($tagTypes as $type)
                                            <flux:select.option value="{{ $type->value }}">
                                                {{ $type->label() }}
                                            </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :style="{ backgroundColor: 'var(--color-' + '{{ $tag->color() }}' + '-100)', color: 'var(--color-' + '{{ $tag->color() }}' + '-800)' }">
                                            {{ $tag->typeLabel() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $tag->music()->count() }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <div class="flex items-center gap-2">
                                        @if($editingTagId === $tag->id)
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="check"
                                                wire:click="updateTag"
                                                wire:loading.attr="disabled"
                                                :title="__('Save')" />
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="x"
                                                wire:click="cancelEditTag"
                                                :title="__('Cancel')" />
                                        @else
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="pencil"
                                                wire:click="editTag({{ $tag->id }})"
                                                :title="__('Edit')" />
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                wire:click="confirmDeleteTag({{ $tag->id }})"
                                                :title="__('Delete')" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end mt-4">
                    <x-action-message on="tag-updated">
                        {{ __('Tag updated successfully.') }}
                    </x-action-message>
                    <x-action-message on="tag-deleted">
                        {{ __('Tag deleted successfully.') }}
                    </x-action-message>
                </div>
                @else
                <div class="text-center py-8 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                    <flux:icon name="tag" class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No tags yet') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Create your first tag to get started.') }}</p>
                </div>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteConfirm)
    <flux:modal wire:model="showDeleteConfirm" max-width="md">
        <flux:heading size="lg">{{ __('Delete Tag') }}</flux:heading>
        <flux:text class="text-sm text-gray-600 dark:text-gray-400 mt-2">
            {{ __('Are you sure you want to delete this tag? This action cannot be undone.') }}
        </flux:text>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="cancelDelete">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="danger"
                wire:click="deleteTag"
                wire:loading.attr="disabled">
                {{ __('Delete') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif
</div>
