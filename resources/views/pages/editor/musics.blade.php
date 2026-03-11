<div class="mx-auto w-full px-1 sm:px-6 lg:px-8">
    <!-- Action messages -->
    <div class="mb-4 flex justify-end">
        <x-action-message on="music-deleted">
            {{ __('Music piece deleted.') }}
        </x-action-message>
        <x-action-message on="error" />
    </div>

    <div class="space-y-6">
        <livewire:pages.editor.musics-table />
    </div>

    <!-- Create modal -->
    <flux:modal wire:model="showCreateModal" max-width="lg">
        <flux:heading size="lg">{{ __('Create Music Piece') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input
                    wire:model="title"
                    :placeholder="__('Enter music piece title')"
                    autofocus />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Subtitle') }}</flux:label>
                <flux:input
                    wire:model="subtitle"
                    :placeholder="__('Enter subtitle')" />
                <flux:error name="subtitle" />
            </flux:field>

            <flux:field>
                <flux:checkbox
                    wire:model="isPrivate"
                    :label="__('Make this music piece private (only visible to you)')" />
                <flux:description>{{ __('Private music pieces are only visible to you and cannot be seen by other users.') }}</flux:description>
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="$set('showCreateModal', false)">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="store">
                {{ __('Create') }}
            </flux:button>
        </div>
    </flux:modal>

    <livewire:pages.editor.music-audit-modal />
    <livewire:error-report />
</div>
