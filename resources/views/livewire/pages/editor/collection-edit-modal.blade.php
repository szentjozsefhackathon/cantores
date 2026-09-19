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

        @if($canVerify)
        <flux:field>
            <flux:label>{{ __('Display priority') }}</flux:label>
            <flux:description>{{ __('Controls which collections are shown first when a piece belongs to many. Lower numbers rank higher; the default is 100.') }}</flux:description>
            <flux:input
                type="number"
                wire:model="priority"
                min="0"
                max="65535"
            />
            <flux:error name="priority" />
        </flux:field>
        @endif

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

        <div class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <div>
                <flux:heading size="sm">{{ __('Diatár sources') }}</flux:heading>
                <flux:description>{{ __('Associate the DTX books that can represent this collection, then choose at most one default.') }}</flux:description>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="diatarSearch"
                icon="magnifying-glass"
                :placeholder="__('Search Diatár sources')"
            />

            <div class="max-h-52 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                @forelse($this->diatarBooks() as $book)
                    <label wire:key="diatar-book-{{ $book->id }}" class="flex cursor-pointer items-start gap-3 rounded-md p-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <flux:checkbox wire:model.live="selectedDiatarBookIds" value="{{ $book->id }}" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ $book->title }} — {{ $book->source_path }}</span>
                            @if(! $book->available)
                                <span class="block text-xs text-red-600 dark:text-red-400">
                                    {{ __('Unavailable') }}: {{ $book->unavailable_reason }}
                                </span>
                            @elseif($book->short_name)
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $book->short_name }}</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <flux:text class="p-2 text-sm text-gray-500">{{ __('No Diatár sources match the search.') }}</flux:text>
                @endforelse
            </div>
            <flux:error name="selectedDiatarBookIds" />

            <flux:field>
                <flux:label>{{ __('Default Diatár source') }}</flux:label>
                <flux:select wire:model="defaultDiatarBookId">
                    <flux:select.option value="">{{ __('No default source') }}</flux:select.option>
                    @foreach($this->diatarBooks()->whereIn('id', $selectedDiatarBookIds) as $book)
                        <flux:select.option value="{{ $book->id }}" :disabled="! $book->available">
                            {{ $book->title }} — {{ $book->source_path }}@if(! $book->available) ({{ __('Unavailable') }})@endif
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="defaultDiatarBookId" />
            </flux:field>
        </div>

        @if($canUploadCover)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
            <flux:heading size="sm">{{ __('Cover Image') }}</flux:heading>

            <div class="flex items-center gap-4">
                {{-- Current cover or placeholder --}}
                <div class="shrink-0">
                    @if($currentCoverUrl)
                        <img src="{{ $currentCoverUrl }}" alt="{{ __('Cover') }}"
                             class="w-16 h-16 rounded-xl object-cover" />
                    @else
                        <div class="w-16 h-16 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <flux:icon name="book-open" class="w-8 h-8 text-gray-400 dark:text-gray-500" />
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
                            wire:click="uploadCover"
                            wire:loading.attr="disabled"
                            wire:target="photo,uploadCover"
                        >
                            <span wire:loading.remove wire:target="uploadCover">{{ __('Upload') }}</span>
                            <span wire:loading wire:target="uploadCover">{{ __('Uploading…') }}</span>
                        </flux:button>

                        @if($currentCoverUrl)
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="deleteCover"
                            wire:confirm="{{ __('Remove this collection\'s cover image?') }}"
                        >
                            {{ __('Delete Cover') }}
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
