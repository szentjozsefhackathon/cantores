<?php

use App\Models\Music;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    // Component logic is in MusicTagSelector.php
};

?>

<div>
    <!-- Current Tags -->
    @if($music->tags->count())
    <div class="mb-4">
        <flux:label>{{ __('Assigned Tags') }}</flux:label>
        <div class="flex flex-wrap gap-2 mt-2">
            @foreach($music->tags as $tag)
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm">
                <flux:icon :name="$tag->icon()" class="h-4 w-4" />
                <span class="text-gray-900 dark:text-gray-100">{{ $tag->name }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tag->typeLabel() }}</span>
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="x"
                    wire:click="removeTag({{ $tag->id }})"
                    class="!p-0 !h-4 !w-4"
                    :title="__('Remove tag')" />
            </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="mb-4">
        <flux:label>{{ __('Assigned Tags') }}</flux:label>
        <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">
            {{ __('No tags assigned yet.') }}
        </div>
    </div>
    @endif

    <!-- Add Tag Form -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <flux:label>{{ __('Add Tag') }}</flux:label>
        <flux:description>{{ __('Select a tag to assign to this music piece.') }}</flux:description>

        <div class="flex gap-2 mt-2">
            <div class="flex-1">
                <flux:select wire:model="selectedTagId">
                    <option value="">{{ __('Select a tag') }}</option>
                    @foreach($availableTags as $tag)
                    <flux:select.option value="{{ $tag->id }}">
                        {{ $tag->name }} ({{ $tag->typeLabel() }})
                    </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button
                variant="primary"
                wire:click="addTag"
                wire:loading.attr="disabled"
                icon="plus">
                {{ __('Add') }}
            </flux:button>
        </div>

        <div class="flex justify-end mt-2">
            <x-action-message on="tag-added">
                {{ __('Tag added.') }}
            </x-action-message>
            <x-action-message on="tag-removed">
                {{ __('Tag removed.') }}
            </x-action-message>
        </div>
    </div>
</div>
