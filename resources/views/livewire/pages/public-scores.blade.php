<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <flux:heading size="2xl">{{ __('Free sheet music') }}</flux:heading>
            <flux:subheading>
                {{ __('Public domain and Creative Commons scores you may download, print and sing. Every item here has been checked by an editor.') }}
                <a href="{{ route('score-rights') }}" class="underline">{{ __('What may be published here') }}</a>
            </flux:subheading>
        </div>

        <flux:card class="mb-6 p-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <flux:field>
                    <flux:input
                        wire:model.live.debounce.500ms="search"
                        icon="magnifying-glass"
                        size="sm"
                        :placeholder="__('Title')" />
                </flux:field>

                <flux:field>
                    <flux:select wire:model.live="license" size="sm">
                        <flux:select.option value="">{{ __('Any licence') }}</flux:select.option>
                        @foreach($licenses as $licenseOption)
                        <flux:select.option value="{{ $licenseOption->value }}">{{ $licenseOption->shortCode() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:select wire:model.live="format" size="sm">
                        <flux:select.option value="">{{ __('Any notation') }}</flux:select.option>
                        @foreach($formats as $formatOption)
                        <flux:select.option value="{{ $formatOption->value }}">{{ $formatOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:select wire:model.live="collection" size="sm">
                        <flux:select.option value="">{{ __('Any collection') }}</flux:select.option>
                        @foreach($collections as $collectionOption)
                        <flux:select.option value="{{ $collectionOption->id }}">{{ $collectionOption->abbreviation ?: $collectionOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:select wire:model.live="genre" size="sm">
                        <flux:select.option value="">{{ __('Any genre') }}</flux:select.option>
                        @foreach($genres as $genreOption)
                        <flux:select.option value="{{ $genreOption->id }}">{{ $genreOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
        </flux:card>

        @if($scores->isEmpty())
        <flux:card class="p-8 text-center">
            <flux:text class="text-zinc-500">{{ __('Nothing matches those filters yet.') }}</flux:text>
            <div class="mt-4">
                <flux:button size="sm" variant="outline" wire:click="resetFilters">{{ __('Clear filters') }}</flux:button>
            </div>
        </flux:card>
        @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($scores as $score)
            @php($slug = \Illuminate\Support\Str::slug($score->title) ?: 'kotta')
            <flux:card wire:key="public-score-{{ $score->id }}" class="flex flex-col gap-3 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('public-scores.show', ['score' => $score, 'slug' => $slug]) }}" class="hover:underline">
                            <flux:heading size="lg" class="truncate">{{ $score->title }}</flux:heading>
                        </a>
                        @if($score->music?->authors->isNotEmpty())
                        <flux:text class="truncate text-sm text-zinc-500">{{ $score->music->authors->pluck('name')->implode(', ') }}</flux:text>
                        @endif
                    </div>
                    <x-score-license-badge :publication="$score->publication" />
                </div>

                @if($score->hasIncipit())
                <a href="{{ route('public-scores.show', ['score' => $score, 'slug' => $slug]) }}">
                    <x-incipit-image :src="$score->publicIncipitUrl()" :alt="$score->title" />
                </a>
                @endif

                <div class="mt-auto flex flex-wrap items-center gap-2">
                    <x-score-format-badge :format="$score->format" />
                    @foreach($score->music?->collections ?? [] as $collection)
                    <flux:badge size="sm" color="zinc">{{ $collection->abbreviation ?: $collection->title }}</flux:badge>
                    @endforeach
                </div>
            </flux:card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $scores->links() }}
        </div>
        @endif
    </div>
</div>
