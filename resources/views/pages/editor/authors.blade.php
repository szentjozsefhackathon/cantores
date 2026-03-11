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
        <!-- Create button -->
        @auth
        <div class="flex justify-end">
            <flux:button
                variant="primary"
                icon="plus"
                wire:click="create"
            >
                {{ __('Create Author') }}
            </flux:button>
        </div>
        @endauth

        <livewire:pages.editor.authors-table />
    </div>

    <!-- Create modal -->
    <flux:modal wire:model="showCreateModal" max-width="md">
        <flux:heading size="lg">{{ __('Create Author') }}</flux:heading>

        <div class="mt-2 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Use Last Name, First Name format for Non-Hungarian authors (e.g., Bach, Johann Sebastian).') }}</flux:label>
                <flux:input
                    wire:model="name"
                    :placeholder="__('Enter author name')"
                    autofocus
                    autocomplete="off"
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

    <!-- Child modal components -->
    <livewire:pages.editor.author-edit-modal />
    <livewire:pages.editor.author-audit-modal />

    <livewire:error-report />
</div>
