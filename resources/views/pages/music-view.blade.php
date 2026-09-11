<div class="py-5 sm:py-6">
    @php
        $musicRelations = $music->allMusicRelations();
        $visibleCollections = $music->displayCollections(auth()->user());
        $hasReferences = $visibleCollections->isNotEmpty() || $music->scriptureReferences->isNotEmpty() || $musicRelations->isNotEmpty();
        $downloadableScores = $music->publishedScores()->with('publication')->get();
        $hasResources = $downloadableScores->isNotEmpty() || $music->urls->isNotEmpty();
        $incipitScores = $music->visibleIncipitScores(auth()->user());
    @endphp
    <div data-page-shell class="mx-auto w-full space-y-6 px-4 sm:px-6 lg:max-w-4xl lg:px-8">
        <header class="space-y-4 border-b border-zinc-200 pb-5 dark:border-zinc-700">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1 space-y-2">
                    <flux:heading level="1" size="xl" class="break-words text-2xl! sm:text-3xl!">{{ $music->title }}</flux:heading>
                    @if($music->subtitle)
                        <flux:subheading class="break-words">{{ $music->subtitle }}</flux:subheading>
                    @endif
                    @php
                        $visibleAuthors = $music->authors->filter(fn($author) => auth()->user() ? auth()->user()->can('view', $author) : !$author->is_private);
                    @endphp
                    @if($visibleAuthors->isNotEmpty() || $music->genres->isNotEmpty() || $music->tags->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach($visibleAuthors as $author)
                                <a href="{{ route('author-view', $author) }}" wire:key="music-author-{{ $author->id }}" class="flex max-w-full items-center gap-2 rounded-lg bg-zinc-50 py-1 pr-2 pl-1 text-sm text-zinc-700 transition-colors hover:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                    @if($author->avatarThumbUrl())
                                        <img src="{{ $author->avatarThumbUrl() }}" alt="" class="size-8 shrink-0 rounded object-cover" />
                                    @else
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded bg-zinc-200 dark:bg-zinc-700">
                                            <flux:icon name="user" class="size-4 text-zinc-400" />
                                        </span>
                                    @endif
                                    <span class="min-w-0 break-words">{{ $author->name }}</span>
                                    <x-author-type-icon :type="$author->pivot->author_type" />
                                    <livewire:verification-icon :fieldName="'authors'" :music="$music" :pivotReference="$author->id" />
                                </a>
                            @endforeach
                            @foreach($music->genres as $genre)
                                <span wire:key="music-genre-{{ $genre->id }}" data-music-genre class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900/40 dark:text-blue-200" title="{{ $genre->label() }}">
                                    <flux:icon :name="$genre->icon()" class="size-4 shrink-0" />
                                    <span class="break-words">{{ $genre->label() }}</span>
                                </span>
                            @endforeach
                            @foreach($music->tags as $tag)
                                <span wire:key="music-tag-{{ $tag->id }}" class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs break-words dark:bg-gray-800">
                                    <flux:icon :name="$tag->icon()" class="size-4 shrink-0" />
                                    <span class="text-gray-900 dark:text-gray-100">{{ $tag->name }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $tag->typeLabel() }}</span>
                                    @php
                                        $tagVerified = $music->verifications()
                                            ->where('field_name', 'tag')
                                            ->where('pivot_reference', $tag->id)
                                            ->where('status', 'verified')
                                            ->exists();
                                    @endphp
                                    @if($tagVerified)
                                        <flux:icon name="check" variant="solid" class="h-3 w-3 text-green-500" title="{{ __('Verified') }}" />
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                    @if($incipitScores->isNotEmpty())
                        <div class="max-w-xl py-1"
                            x-data="{ current: 0, total: {{ $incipitScores->count() }} }">
                            <div class="flex items-center gap-2">
                                @if($incipitScores->count() > 1)
                                    <flux:button size="sm" variant="ghost"
                                        x-on:click.prevent="current = (current - 1 + total) % total"
                                        class="shrink-0"
                                        :aria-label="__('Previous')">
                                        <flux:icon name="chevron-left" class="h-4 w-4" />
                                    </flux:button>
                                @endif
                                <div class="flex-1 min-w-0 overflow-hidden max-h-24">
                                    @foreach($incipitScores as $i => $incipitItem)
                                        <div wire:key="incipit-{{ $incipitItem->id }}" x-show="current === {{ $i }}" @if($i > 0) x-cloak @endif>
                                            <x-incipit-image class="max-w-full"
                                                :src="$incipitItem->public_preview ? $incipitItem->publicIncipitUrl() : $incipitItem->incipitUrl()"
                                                :alt="$incipitItem->title"
                                                img-class="block h-auto max-h-20 w-auto max-w-full" />
                                        </div>
                                    @endforeach
                                </div>
                                @if($incipitScores->count() > 1)
                                    <flux:button size="sm" variant="ghost"
                                        x-on:click.prevent="current = (current + 1) % total"
                                        class="shrink-0"
                                        :aria-label="__('Next')">
                                        <flux:icon name="chevron-right" class="h-4 w-4" />
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                @auth
                    <flux:button size="sm" variant="primary" icon="pencil" :href="route('music-editor', $music)" class="self-start shrink-0">
                        {{ __('Edit Music Piece') }}
                    </flux:button>
                @endauth
            </div>
        </header>

        <div @class([
        'grid grid-cols-1 items-start gap-6 lg:gap-8',
        'lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]' => $hasReferences && $hasResources,
            ])>
            @if($hasReferences)
                <aside class="min-w-0 space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 sm:max-w-md dark:border-zinc-700 dark:bg-zinc-800/30">
                    @if($visibleCollections->isNotEmpty())
                        <div>
                            <flux:heading level="2" size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Collections') }}</flux:heading>
                            <div data-collections class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($visibleCollections as $collection)
                                    @php
                                        $collectionVerified = $music->verifications()
                                            ->where('field_name', 'collection')
                                            ->where('pivot_reference', $collection->id)
                                            ->where('status', 'verified')
                                            ->exists();
                                        $collectionLocation = collect([
                                            $collection->pivot->page_number ? __('Page').': '.$collection->pivot->page_number : null,
                                            $collection->pivot->order_number ? __('Order').': '.$collection->pivot->order_number : null,
                                        ])->filter()->implode(' · ');
                                    @endphp
                                    <a href="{{ route('collection-view', $collection) }}" class="flex flex-wrap items-center gap-x-2 gap-y-0.5 rounded-sm py-1.5 transition-colors hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:hover:bg-zinc-800">
                                        @if($collection->coverThumbUrl())
                                            <img src="{{ $collection->coverThumbUrl() }}" alt="" loading="lazy" class="size-8 shrink-0 rounded object-contain" />
                                        @else
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded bg-zinc-200 dark:bg-zinc-700">
                                                <flux:icon name="book-open" class="size-4 text-zinc-400" />
                                            </span>
                                        @endif
                                        <span class="min-w-0 flex-1 text-sm">
                                            <span class="break-words font-medium">{{ $collection->title }}</span>
                                            @if($collection->abbreviation)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $collection->abbreviation }})</span>
                                            @endif
                                            @if($collectionVerified)
                                                <flux:icon name="check" variant="solid" class="inline h-4 w-4 text-green-500" title="{{ __('Verified') }}" />
                                            @endif
                                        </span>
                                        @if($collectionLocation)
                                            <span class="ml-auto shrink-0 text-xs whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $collectionLocation }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($music->scriptureReferences->isNotEmpty())
                        <div>
                            <flux:heading level="2" size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Scripture References') }}</flux:heading>
                            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($music->scriptureReferences as $scriptureReference)
                                    <a href="https://szentiras.eu/{{ rawurlencode($scriptureReference->reference) }}" target="_blank" rel="noopener noreferrer" class="block rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                                        <div class="flex items-start justify-between gap-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <div class="min-w-0">
                                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                                    <flux:text class="break-words font-medium">{{ $scriptureReference->reference }}</flux:text>
                                                    <flux:badge color="zinc" size="sm">{{ $scriptureReference->reference_type->label() }}</flux:badge>
                                                </div>
                                                @if($scriptureReference->text)
                                                    <flux:text class="text-sm text-gray-500 dark:text-gray-400 mt-1 whitespace-pre-line">{{ $scriptureReference->text }}</flux:text>
                                                @endif
                                            </div>
                                            <flux:icon name="external-link" class="h-4 w-4 text-gray-400 shrink-0 mt-0.5" />
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($musicRelations->isNotEmpty())
                        <div>
                            <flux:heading level="2" size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Related Music') }}</flux:heading>
                            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($musicRelations as $relation)
                                    @php $partner = $relation->partnerFor($music); @endphp
                                    <a href="{{ route('music-view', $partner) }}" class="block rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <div>
                                                <flux:text class="break-words font-medium">{{ $partner->title }}</flux:text>
                                                @if($partner->subtitle)
                                                    <flux:text class="text-xs text-gray-500 dark:text-gray-400">{{ $partner->subtitle }}</flux:text>
                                                @endif
                                            </div>
                                            <div class="shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">
                                                {{ \App\MusicRelationshipType::from($relation->relationship_type)->label() }}
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>
            @endif
            @if($hasResources)
                <div class="min-w-0 space-y-6">
                    @if($downloadableScores->isNotEmpty())
                        <div>
                            <flux:heading level="2" size="sm" class="mb-3 text-neutral-600 dark:text-neutral-400">
                                {{ __('Free to download') }}
                            </flux:heading>

                            <div class="grid grid-cols-1 gap-3">
                                @foreach($downloadableScores as $publicScore)
                                    <x-score-card wire:key="public-score-{{ $publicScore->id }}"
                                        :score="$publicScore"
                                        :href="route('public-scores.show', ['score' => $publicScore, 'slug' => \Illuminate\Support\Str::slug($publicScore->title)])"
                                        :incipit-src="$publicScore->hasIncipit() ? $publicScore->publicIncipitUrl() : null">
                                        <x-slot:meta>
                                            <x-score-license-badge :publication="$publicScore->publication" />
                                        </x-slot:meta>
                                    </x-score-card>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($music->urls->isNotEmpty())
                        <div>
                            <flux:heading level="2" size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('External Links') }}</flux:heading>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach($music->urls as $url)
                                    @php
                                        $urlLabel = \App\MusicUrlLabel::tryFrom($url->label);
                                        $color = $urlLabel?->color() ?? 'text-gray-500';
                                        $icon = $urlLabel?->icon() ?? 'link';
                                        $labelText = $urlLabel?->label() ?? ucfirst(str_replace('_', ' ', $url->label ?? ''));
                                    @endphp
                                    <a href="{{ $url->url }}" target="_blank" rel="noopener noreferrer" class="block">
                                        <flux:card class="p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors" variant="outline">
                                            <div class="flex items-start gap-3">
                                                <flux:icon :name="$icon" class="h-5 w-5 {{ $color }} shrink-0 mt-0.5" />
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <flux:text class="font-medium text-sm truncate">{{ $labelText }}</flux:text>
                                                        @php
                                                            $urlVerified = $music->verifications()
                                                                ->where('field_name', 'url')
                                                                ->where('pivot_reference', $url->id)
                                                                ->where('status', 'verified')
                                                                ->exists();
                                                        @endphp
                                                        @if($urlVerified)
                                                            <flux:icon name="check" variant="solid" class="h-3 w-3 text-green-500 shrink-0" title="{{ __('Verified') }}" />
                                                        @endif
                                                    </div>
                                                    <flux:text class="text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $url->url }}">{{ Str::limit($url->url, 40) }}</flux:text>
                                                </div>
                                                <flux:icon name="external-link" class="h-4 w-4 text-gray-400 shrink-0 mt-0.5" />
                                            </div>
                                        </flux:card>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
        @auth
            <div data-own-scores>
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <flux:heading level="2" size="sm" class="text-neutral-600 dark:text-neutral-400">{{ __('My Private Scores') }}</flux:heading>
                    <flux:button size="sm" variant="primary" icon="plus" :href="route('scores.create', ['music' => $music->id])" wire:navigate>
                        {{ __('Create Score') }}
                    </flux:button>
                </div>

                @php
                    $myScores = $music->scores()
                        ->where('user_id', auth()->id())
                        ->latest('updated_at')
                        ->get();
                @endphp

                @if($myScores->isNotEmpty())
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach($myScores as $score)
                            <x-score-card wire:key="my-score-{{ $score->id }}"
                                :score="$score"
                                :href="route('scores.edit', $score)">
                                <x-slot:meta>
                                    <x-score-format-badge :format="$score->format" />
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $score->updated_at->translatedFormat('Y-m-d') }}</span>
                                </x-slot:meta>
                                <x-slot:actions>
                                    <flux:button size="sm" variant="ghost" icon="pencil" :href="route('scores.edit', $score)" wire:navigate :title="__('Edit')" />
                                </x-slot:actions>
                            </x-score-card>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50">
                        <flux:text>{{ __('No private scores are attached to this music yet.') }}</flux:text>
                    </div>
                @endif
            </div>
        @endauth
        @if($musicPlans->isNotEmpty())
            <div>
                <flux:heading level="2" size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Music Plans') }}</flux:heading>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach($musicPlans as $plan)
                        <livewire:music-plan-card :musicPlan="$plan" :key="$plan->id" readonly="true" />
                    @endforeach
                </div>
                @if($musicPlans->hasPages())
                    <div class="mt-4">
                        {{ $musicPlans->links() }}
                    </div>
                @endif
            </div>
        @endif

        <footer class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" variant="ghost" icon="history" x-on:click="$dispatch('show-music-audit-log', { musicId: {{ $music->id }} })">
                    {{ __('View Audit Log') }}
                </flux:button>
                @auth
                    <flux:button size="sm" variant="ghost" icon="flag" wire:click="dispatch('openErrorReportModal', { resourceId: {{ $music->id }}, resourceType: 'music' })">
                        {{ __('Report an Issue') }}
                    </flux:button>
                @endauth
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                <flux:badge color="{{ $music->is_private ? 'zinc' : 'green' }}" size="sm">
                    {{ $music->is_private ? __('Private') : __('Public') }}
                </flux:badge>
                <span class="font-mono">#{{ $music->id }}</span>
                @if($music->custom_id)
                    <span>{{ __('Custom ID') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $music->custom_id }}</span></span>
                @endif
                <span>{{ __('Created by') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $music->user?->display_name ?? '–' }}</span></span>
                <span>{{ __('Created') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $music->created_at->translatedFormat('Y-m-d') }}</span></span>
                <span>{{ __('Updated') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $music->updated_at->translatedFormat('Y-m-d') }}</span></span>
            </div>
        </footer>
    </div>

    <livewire:pages.editor.music-audit-modal />
    <livewire:error-report />
</div>
