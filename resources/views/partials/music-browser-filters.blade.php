{{-- Shared filter grid used by both the Musics editor page and the Music Search component.
     The including Livewire component must expose: search, collectionFreeText, collectionFilter,
     authorFreeText, authorFilter, tagFilters, $this->collections, $this->authors, $this->tags. --}}
<div class="mt-4" x-data="{ active: 'search' }">

    {{-- Mobile: icon button bar (hidden on sm+) --}}
    <div class="flex gap-1 sm:hidden mb-3">
        <flux:button type="button" variant="ghost" size="sm" icon="magnifying-glass"
            @click="active = active === 'search' ? null : 'search'"
            x-bind:class="active === 'search' ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
            title="{{ __('Title') }}" />
        <flux:button type="button" variant="ghost" size="sm" icon="tag"
            @click="active = active === 'tags' ? null : 'tags'"
            x-bind:class="active === 'tags' ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
            title="{{ __('Tags') }}" />
        <flux:button type="button" variant="ghost" size="sm" icon="hashtag"
            @click="active = active === 'collection-text' ? null : 'collection-text'"
            x-bind:class="active === 'collection-text' ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
            title="{{ __('Collection search') }}" />
        <flux:button type="button" variant="ghost" size="sm" icon="book-open"
            @click="active = active === 'collection-select' ? null : 'collection-select'"
            x-bind:class="active === 'collection-select' ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
            title="{{ __('Collection') }}" />
        <flux:button type="button" variant="ghost" size="sm" icon="user"
            @click="active = active === 'author-text' ? null : 'author-text'"
            x-bind:class="active === 'author-text' ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
            title="{{ __('Author search') }}" />
        <flux:button type="button" variant="ghost" size="sm" icon="users"
            @click="active = active === 'author-select' ? null : 'author-select'"
            x-bind:class="active === 'author-select' ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
            title="{{ __('Author') }}" />
    </div>

    {{-- Filter inputs: on mobile shown one at a time via Alpine; on sm+ always shown in 2-col grid --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">

        {{-- Title search --}}
        <div x-show="active === 'search'" class="sm:!block"
            x-init="if (window.innerWidth >= 640) $nextTick(() => $el.querySelector('input')?.focus())">
            <flux:field>
                <flux:input
                    type="search"
                    wire:model.live.debounce.500ms="search"
                    :placeholder="__('Title, subtitle, etc.')"
                    autocomplete="off" />
            </flux:field>
        </div>

        {{-- Tags --}}
        <div x-show="active === 'tags'" class="sm:!block text-sm">
            <flux:field>
                <x-mary-choices
                    placeholder="{{ __('Tags (all selected required)') }}"
                    wire:model.live="tagFilters"
                    :options="$this->tagOptions">
                    @scope('item', $option)
                        <x-mary-list-item :item="$option">
                            <x-slot:avatar>
                                <flux:icon :name="$option['icon'] ?? null" class="h-4 w-4" />
                            </x-slot:avatar>
                        </x-mary-list-item>
                    @endscope
                    @scope('selection', $selectedTags)
                        @foreach ($selectedTags as $tagId)
                            @php $tag = $this->tagsById->get($tagId); @endphp
                            @if ($tag)
                                <div class="inline">
                                    <flux:icon :name="$tag->icon()" class="h-4 w-4 inline" />
                                    <span class="text-gray-900 dark:text-gray-100 inline">{{ $tag->name }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tag->typeLabel() }}</span>
                                </div>
                            @endif
                        @endforeach
                    @endscope
                </x-mary-choices>
            </flux:field>
        </div>

        {{-- Collection free-text --}}
        <div x-show="active === 'collection-text'" class="sm:!block">
            <flux:field>
                <flux:input
                    type="search"
                    wire:model.live.debounce.500ms="collectionFreeText"
                    :placeholder="__('Abbreviation, order number, etc.')" />
            </flux:field>
        </div>

        {{-- Collection dropdown --}}
        <div x-show="active === 'collection-select'" class="sm:!block">
            <flux:field>
                <flux:select wire:model.live="collectionFilter">
                    <option value="">{{ __('All Collections') }}</option>
                    @foreach ($this->collections as $collection)
                        <option value="{{ $collection->title }}">{{ $collection->title }} ({{ $collection->abbreviation }})</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        {{-- Author free-text --}}
        <div x-show="active === 'author-text'" class="sm:!block">
            <flux:field>
                <flux:input
                    type="search"
                    wire:model.live.debounce.500ms="authorFreeText"
                    :placeholder="__('Author name...')" />
            </flux:field>
        </div>

        {{-- Author dropdown --}}
        <div x-show="active === 'author-select'" class="sm:!block">
            <flux:field>
                <flux:select wire:model.live="authorFilter">
                    <option value="">{{ __('All Authors') }}</option>
                    @foreach ($this->authors as $author)
                        <option value="{{ $author->name }}">{{ $author->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

    </div>
</div>
