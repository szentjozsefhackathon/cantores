<flux:modal wire:model="show" max-width="md">
    <flux:heading size="lg">{{ __('Edit Author') }}</flux:heading>

    <div class="mt-2 space-y-4">
        <flux:field required>
            <flux:label>{{ __('Use Last Name, First Name format for Non-Hungarian authors (e.g., Bach, Johann Sebastian).') }}</flux:label>
            <flux:input
                wire:model="name"
                :placeholder="__('Enter author name')"
            />
            <flux:error name="name" />
        </flux:field>

        @if($canChangePrivacy)
        <flux:field>
            <flux:checkbox
                wire:model="isPrivate"
                :label="__('Make this author private (only visible to you)')"
            />
            <flux:description>{{ __('Private authors are only visible to you and cannot be seen by other users.') }}</flux:description>
        </flux:field>
        @endif
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <flux:button
            variant="ghost"
            wire:click="$set('show', false)"
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
