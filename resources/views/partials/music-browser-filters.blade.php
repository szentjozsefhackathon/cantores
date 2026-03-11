{{-- Shared filter grid used by both the Musics editor page and the Music Search component.
     The including Livewire component must expose: search, collectionFreeText, collectionFilter,
     authorFreeText, authorFilter, filter, tagFilters, $this->collections, $this->authors, $this->tags. --}}
<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
    {{-- Title / full-text search (2 cols on lg) --}}
    <div class="lg:col-span-2">
        <flux:field>
            <flux:input
                type="search"
                wire:model.live.debounce.500ms="search"
                :placeholder="__('Title, subtitle, etc.')" 
                autocomplete="off"
                />
                
        </flux:field>
    </div>

    {{-- Collection free-text --}}
    <div>
        <flux:field>
            <flux:input
                type="search"
                wire:model.live.debounce.500ms="collectionFreeText"
                :placeholder="__('Abbreviation, order number, etc.')" />
        </flux:field>
    </div>

    {{-- Collection dropdown --}}
    <div>
        <flux:field>
            <flux:select wire:model.live="collectionFilter">
                <option value="">{{ __('All Collections') }}</option>
                @foreach ($this->collections as $collection)
                    <option value="{{ $collection->title }}">{{ $collection->title }} ({{ $collection->abbreviation }})</option>
                @endforeach
            </flux:select>
        </flux:field>
    </div>

    {{-- Author free-text (2 cols on lg) --}}
    <div class="lg:col-span-2">
        <flux:field>
            <flux:input
                type="search"
                wire:model.live.debounce.500ms="authorFreeText"
                :placeholder="__('Author name...')" />
        </flux:field>
    </div>

    {{-- Author dropdown --}}
    <div>
        <flux:field>
            <flux:select wire:model.live="authorFilter">
                <option value="">{{ __('All Authors') }}</option>
                @foreach ($this->authors as $author)
                    <option value="{{ $author->name }}">{{ $author->name }}</option>
                @endforeach
            </flux:select>
        </flux:field>
    </div>

    @auth
    {{-- Visibility filter --}}
    <div>
        <x-mary-choices placeholder="{{ __('Visibility') }}" single wire:model.live="filter" :options="array_filter([
            ['id' => 'all', 'name' => __('All'), 'icon' => 'lucide.layers'],
            ['id' => 'public', 'name' => __('Public'), 'icon' => 'lucide.globe'],
            ['id' => 'private', 'name' => __('Private'), 'icon' => 'lucide.globe-lock'],
            ['id' => 'mine', 'name' => __('My items'), 'icon' => 'o-user'],
        ])">
            @scope('item', $option)
                <x-mary-list-item :item="$option">
                    <x-slot:avatar>
                        <x-mary-icon :name="$option['icon']" />
                    </x-slot:avatar>
                </x-mary-list-item>
            @endscope
        </x-mary-choices>
    </div>
    @endauth

    {{-- Tag filter – AND logic: music must have ALL selected tags (2 cols on lg) --}}
    <div class="lg:col-span-2 text-sm">
        <flux:field>
            <x-mary-choices
                placeholder="{{ __('Tags (all selected required)') }}"
                wire:model.live="tagFilters"
                :options="$this->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name . ($tag->type ? ' (' . $tag->type->label() . ')' : ''),
                    'icon' => $tag->icon(),
                ])->toArray()">
                @scope('item', $option)
                    <x-mary-list-item :item="$option">
                        <x-slot:avatar>
                            <flux:icon :name="$option['icon'] ?? null" class="h-4 w-4" />
                        </x-slot:avatar>
                    </x-mary-list-item>
                @endscope
                @scope('selection', $selectedTags)
                    @foreach ($selectedTags as $tagId)
                        @php $tag = $this->tags->firstWhere('id', $tagId); @endphp
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
</div>
