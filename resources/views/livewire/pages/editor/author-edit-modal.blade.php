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

        @if($canUploadAvatar)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
            <flux:heading size="sm">{{ __('Avatar') }}</flux:heading>

            <div class="flex items-center gap-4">
                {{-- Current avatar or placeholder --}}
                <div class="shrink-0">
                    @if($currentAvatarUrl)
                        <img src="{{ $currentAvatarUrl }}" alt="{{ __('Avatar') }}"
                             class="w-16 h-16 rounded-xl object-cover" />
                    @else
                        <div class="w-16 h-16 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <flux:icon name="user" class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                        </div>
                    @endif
                </div>

                <div class="flex-1 space-y-2">
                    {{-- File input --}}
                    <flux:field>
                        <flux:input
                            type="file"
                            wire:model="photo"
                            accept="image/*"
                        />
                        <flux:description>{{ __('JPG, PNG, GIF or WebP. Max 2 MB. Will be cropped to square.') }}</flux:description>
                        <flux:error name="photo" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Vertical crop') }}</flux:label>
                        <flux:select wire:model="cropAlign" size="sm">
                            <flux:select.option value="top">{{ __('Top') }}</flux:select.option>
                            <flux:select.option value="center">{{ __('Center') }}</flux:select.option>
                            <flux:select.option value="bottom">{{ __('Bottom') }}</flux:select.option>
                        </flux:select>
                    </flux:field>

                    <div class="flex gap-2">
                        <flux:button
                            size="sm"
                            variant="filled"
                            wire:click="uploadAvatar"
                            wire:loading.attr="disabled"
                            wire:target="photo,uploadAvatar"
                        >
                            <span wire:loading.remove wire:target="uploadAvatar">{{ __('Upload') }}</span>
                            <span wire:loading wire:target="uploadAvatar">{{ __('Uploading…') }}</span>
                        </flux:button>

                        @if($currentAvatarUrl)
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="deleteAvatar"
                            wire:confirm="{{ __('Remove this author\'s avatar?') }}"
                        >
                            {{ __('Delete Avatar') }}
                        </flux:button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Photo license --}}
            <flux:field>
                <flux:label>{{ __('Photo license') }}</flux:label>
                <div class="flex gap-2">
                    <flux:input
                        wire:model="photoLicense"
                        :placeholder="__('e.g. CC BY-SA 4.0, public domain…')"
                        class="flex-1"
                    />
                    <flux:button size="sm" wire:click="savePhotoLicense">{{ __('Save') }}</flux:button>
                </div>
                <flux:error name="photoLicense" />
            </flux:field>
        </div>
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
