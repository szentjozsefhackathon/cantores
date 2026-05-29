<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Music summary card -->
        <div class="mb-6">
            <livewire:music-card :music="$music" />
        </div>

        <flux:card class="p-5">
            <div class="mb-6">
                <flux:heading size="xl">{{ $music->title }}</flux:heading>
                @if($music->subtitle)
                    <flux:subheading>{{ $music->subtitle }}</flux:subheading>
                @endif
                @auth
                <flux:button variant="ghost" icon="flag" wire:click="dispatch('openErrorReportModal', { resourceId: {{ $music->id }}, resourceType: 'music' })">
                    {{ __('Report an Issue') }}
                </flux:button>
                @endauth
            </div>

            <div class="space-y-6">
                <!-- Genres -->
                @if($music->genres->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Genres') }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($music->genres as $genre)
                            <flux:badge color="blue" size="sm" :icon="$genre->icon()">
                                {{ $genre->label() }}
                            </flux:badge>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Tags -->
                @if($music->tags->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Tags') }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($music->tags as $tag)
                            <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm">
                                <flux:icon :name="$tag->icon()" class="h-4 w-4" />
                                <span class="text-gray-900 dark:text-gray-100">{{ $tag->name }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tag->typeLabel() }}</span>
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
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Authors -->
                @php
                    $visibleAuthors = $music->authors->filter(fn($author) => auth()->user() ? auth()->user()->can('view', $author) : !$author->is_private);
                @endphp
                @if($visibleAuthors->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Authors') }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($visibleAuthors as $author)
                            <a href="{{ route('author-view', $author) }}" class="inline-block">
                                <flux:badge color="purple" size="sm" class="hover:bg-purple-600 transition-colors flex items-center gap-1">
                                    {{ $author->name }}
                                    <livewire:verification-icon :fieldName="'authors'" :music="$music" :pivotReference="$author->id" />
                                </flux:badge>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Collections -->
                @php
                    $visibleCollections = $music->collections->filter(fn($collection) => auth()->user() ? auth()->user()->can('view', $collection) : !$collection->is_private);
                @endphp
                @if($visibleCollections->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Collections') }}</flux:heading>
                    <div class="space-y-3">
                        @foreach($visibleCollections as $collection)
                            <a href="{{ route('collection-view', $collection) }}" class="block">
                                <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <div class="flex items-center gap-2">
                                        <div>
                                            <flux:text class="font-medium">{{ $collection->title }}</flux:text>
                                            @if($collection->abbreviation)
                                                <flux:text class="text-sm text-gray-500 dark:text-gray-400">({{ $collection->abbreviation }})</flux:text>
                                            @endif
                                        </div>
                                        @php
                                            $collectionVerified = $music->verifications()
                                                ->where('field_name', 'collection')
                                                ->where('pivot_reference', $collection->id)
                                                ->where('status', 'verified')
                                                ->exists();
                                        @endphp
                                        @if($collectionVerified)
                                            <flux:icon name="check" variant="solid" class="h-4 w-4 text-green-500" title="{{ __('Verified') }}" />
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        @if($collection->pivot->page_number)
                                            {{ __('Page') }}: {{ $collection->pivot->page_number }}
                                        @endif
                                        @if($collection->pivot->order_number)
                                            {{ __('Order') }}: {{ $collection->pivot->order_number }}
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Related Music -->
                @if($music->allMusicRelations()->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Related Music') }}</flux:heading>
                    <div class="space-y-3">
                        @foreach($music->allMusicRelations() as $relation)
                        @php $partner = $relation->partnerFor($music); @endphp
                            <a href="{{ route('music-view', $partner) }}" class="block">
                                <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <div>
                                        <flux:text class="font-medium">{{ $partner->title }}</flux:text>
                                        @if($partner->subtitle)
                                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">{{ $partner->subtitle }}</flux:text>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ \App\MusicRelationshipType::from($relation->relationship_type)->label() }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Music Plans -->
                @if($musicPlans->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Music Plans') }}</flux:heading>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
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

                <!-- URLs -->
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('External Links') }}</flux:heading>
                    @if($music->urls->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($music->urls as $url)
                                @php
                                    $urlLabel = \App\MusicUrlLabel::tryFrom($url->label);
                                    $color = $urlLabel?->color() ?? 'text-gray-500';
                                    $icon = $urlLabel?->icon() ?? 'link';
                                    $labelText = $urlLabel?->label() ?? ucfirst(str_replace('_', ' ', $url->label ?? ''));
                                @endphp
                                <a href="{{ $url->url }}" target="_blank" rel="noopener noreferrer" class="block">
                                    <flux:card class="p-4 hover:shadow-md transition-shadow" variant="outline">
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
                    @else
                        <flux:callout color="zinc" variant="soft">
                            <flux:text>{{ __('No external links available for this music piece.') }}</flux:text>
                        </flux:callout>
                    @endif
                </div>

                @auth
                <!-- Private Scores -->
                <div>
                    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400">{{ __('My Private Scores') }}</flux:heading>
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
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Score') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">{{ __('Incipit') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($myScores as $score)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        <td class="px-3 py-2 text-xs md:text-sm font-medium text-gray-900 dark:text-gray-100">
                                            <a href="{{ route('scores.edit', $score) }}" wire:navigate class="hover:underline text-blue-600 dark:text-blue-400">
                                                {{ $score->title }}
                                            </a>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                                <flux:badge color="zinc" size="sm">{{ $score->format->label() }}</flux:badge>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $score->updated_at->translatedFormat('Y-m-d') }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 hidden md:table-cell">
                                            @if($score->hasIncipit())
                                            <img src="{{ route('scores.incipit', $score) }}"
                                                 alt="{{ __('Incipit') }}"
                                                 class="h-auto max-h-12 w-auto max-w-[200px]" />
                                            @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('No incipit') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs md:text-sm">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                                :href="route('scores.edit', $score)"
                                                wire:navigate
                                                :title="__('Edit')" />
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <flux:callout color="zinc" variant="soft">
                            <flux:text>{{ __('No private scores are attached to this music yet.') }}</flux:text>
                        </flux:callout>
                    @endif
                </div>
                @endauth

            </div>

            <!-- Status bar -->
            <div class="mt-6 pt-3 border-t border-neutral-200 dark:border-neutral-700 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
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
        </flux:card>

        <!-- Actions (only for authenticated users) -->
        @auth
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <flux:button variant="primary" icon="pencil" :href="route('music-editor', $music)">
                {{ __('Edit Music Piece') }}
            </flux:button>
        </div>
        @endauth
    </div>

<livewire:error-report />
</div>