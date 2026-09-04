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

        @if($groups->isEmpty())
        <flux:card class="p-8 text-center">
            <flux:text class="text-zinc-500">{{ __('Nothing matches those filters yet.') }}</flux:text>
            <div class="mt-4">
                <flux:button size="sm" variant="outline" wire:click="resetFilters">{{ __('Clear filters') }}</flux:button>
            </div>
        </flux:card>
        @else
        <div class="grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($groups as $group)
            @php
                $primary = $group->first();
                $music = $primary->music;
                $incipitScore = $group->first(fn ($groupedScore) => $groupedScore->hasIncipit());
                $shown = $group->take(3);
                $remaining = $group->count() - $shown->count();
            @endphp
            <flux:card wire:key="public-score-group-{{ $music?->id ?? 'score-'.$primary->id }}" class="flex h-full flex-col gap-3 p-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <flux:icon name="musical-note" variant="micro" class="shrink-0 text-zinc-400" />
                        @if($music)
                        <a href="{{ route('music-view', $music) }}" wire:navigate class="min-w-0 hover:underline">
                            <flux:heading size="lg" class="truncate">{{ $music->title }}</flux:heading>
                        </a>
                        @else
                        <flux:heading size="lg" class="truncate">{{ $primary->title }}</flux:heading>
                        @endif
                    </div>
                    @if($music?->authors->isNotEmpty())
                    <flux:text class="truncate pl-5 text-sm text-zinc-500">{{ $music->authors->pluck('name')->implode(', ') }}</flux:text>
                    @endif
                </div>

                <div class="flex h-36 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                    @if($incipitScore)
                    <a href="{{ $incipitScore->publicUrl() }}" class="flex h-full w-full items-center justify-center">
                        <x-incipit-image :src="$incipitScore->publicIncipitUrl()" :alt="$incipitScore->title" imgClass="max-h-36 max-w-full object-contain" />
                    </a>
                    @else
                    <flux:icon name="musical-note" class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                    @endif
                </div>

                <div class="mt-auto flex flex-col gap-2">
                    @foreach($shown as $score)
                    <div class="flex flex-wrap items-center gap-1.5 text-sm" wire:key="public-score-{{ $score->id }}">
                        <flux:icon :name="$score->variation_name ? 'layers' : 'document-text'" variant="micro" class="shrink-0 text-zinc-400" />
                        <a href="{{ $score->publicUrl() }}" class="min-w-0 truncate hover:underline">{{ $score->variationLabel() }}</a>
                        <x-score-format-badge :format="$score->format" />
                        <x-score-license-badge :publication="$score->publication" />
                        <span class="inline-flex items-center gap-1 text-xs text-zinc-500">
                            <flux:icon name="user" variant="micro" class="shrink-0" />
                            {{ $score->user->display_name }}
                        </span>
                    </div>
                    @endforeach

                    @if($remaining > 0 && $music)
                    <a href="{{ route('music-view', $music) }}" wire:navigate class="text-xs text-zinc-500 hover:underline">
                        {{ trans_choice('+:count more score|+:count more scores', $remaining, ['count' => $remaining]) }}
                    </a>
                    @endif

                    @if($music?->collections->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        @foreach($music->collections as $collection)
                        <flux:badge size="sm" color="zinc">{{ $collection->abbreviation ?: $collection->title }}</flux:badge>
                        @endforeach
                    </div>
                    @endif
                </div>
            </flux:card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $groups->links() }}
        </div>
        @endif
    </div>
</div>
