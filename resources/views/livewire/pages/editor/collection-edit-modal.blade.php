<flux:modal wire:model="show" max-width="md">
    <flux:heading size="lg">{{ __('Edit Collection') }}</flux:heading>

    <div class="mt-2 space-y-4">
        <flux:field required>
            <flux:label>{{ __('Title') }}</flux:label>
            <flux:input
                wire:model="title"
                :placeholder="__('Enter collection title')"
            />
            <flux:error name="title" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Abbreviation') }}</flux:label>
            <flux:description>{{ __('Optional short form, e.g., ÉE, BWV') }}</flux:description>
            <flux:input
                wire:model="abbreviation"
                :placeholder="__('Enter abbreviation')"
                maxlength="20"
            />
            <flux:error name="abbreviation" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Author') }}</flux:label>
            <flux:description>{{ __('Optional author or publisher') }}</flux:description>
            <flux:input
                wire:model="author"
                :placeholder="__('Enter author name')"
            />
            <flux:error name="author" />
        </flux:field>

        <flux:field>
            <flux:checkbox
                wire:model="isPrivate"
                :label="__('Make this collection private (only visible to you)')"
            />
            <flux:description>{{ __('Private collections are only visible to you and cannot be seen by other users.') }}</flux:description>
        </flux:field>

        <div class="space-y-2">
            <flux:checkbox.group variant="cards" label="{{ __('Select which genres this collection belongs to.') }}">
                @foreach($this->genres() as $genre)
                    <flux:checkbox
                        variant="cards"
                        wire:model="selectedGenres"
                        value="{{ $genre->id }}"
                        :label="$genre->label()"
                        :icon="$genre->icon()"
                    />
                @endforeach
            </flux:checkbox.group>
        </div>
        <flux:error name="selectedGenres" />
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
