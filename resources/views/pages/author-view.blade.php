<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Back button -->
        <div class="mb-6">
            <flux:button
                variant="ghost"
                icon="arrow-left"
                :href="route('music-plans')"
                tag="a"
            >
                {{ __('Back to Music Plans') }}
            </flux:button>
        </div>

        <flux:card class="p-5">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <flux:heading size="xl">{{ __('Author Details') }}</flux:heading>
                    <flux:subheading>{{ $author->name }}</flux:subheading>
                </div>
                
                <!-- Privacy badge -->
                <flux:badge color="{{ $author->is_private ? 'zinc' : 'green' }}" size="lg">
                    {{ $author->is_private ? __('Private') : __('Public') }}
                </flux:badge>
            </div>

            <div class="space-y-6">
                <!-- Basic info -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Music Pieces') }}</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $author->music()->count() }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('Created by') }}</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $author->user?->display_name ?? '–' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">{{ __('ID') }}</flux:heading>
                        <flux:text class="text-base font-semibold">#{{ $author->id }}</flux:text>
                    </div>
                </div>

                <!-- Music pieces by this author -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">{{ __('Music Pieces by this Author') }}</flux:heading>
                        <flux:badge color="blue" size="lg">{{ $author->music()->count() }}</flux:badge>
                    </div>

                    <!-- Search input -->
                    <div class="mb-6">
                        <flux:field>
                            <flux:input
                                type="search"
                                wire:model.live="search"
                                :placeholder="__('Search by title, subtitle, or custom ID...')"
                            />
                        </flux:field>
                    </div>
                    
                    @if($musics->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
                            @foreach($musics as $music)
                                <livewire:music-card :music="$music" :key="'music-card-'.$music->id" />
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="mt-8">
                            {{ $musics->links() }}
                        </div>
                    @else
                        <flux:callout variant="secondary" icon="musical-note">
                            @if($search)
                                {{ __('No music pieces found matching your search.') }}
                            @else
                                {{ __('No music pieces are assigned to this author yet.') }}
                            @endif
                        </flux:callout>
                    @endif
                </div>

                <!-- Additional info -->
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Additional Information') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <flux:icon name="calendar" class="h-4 w-4 text-gray-500" />
                            <span class="text-gray-700 dark:text-gray-300">{{ __('Created') }}: {{ $author->created_at->translatedFormat('Y-m-d') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon name="pencil" class="h-4 w-4 text-gray-500" />
                            <span class="text-gray-700 dark:text-gray-300">{{ __('Updated') }}: {{ $author->updated_at->translatedFormat('Y-m-d') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Actions (only for authenticated users) -->
        @auth
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            @can('update', $author)
                <flux:button variant="primary" icon="pencil" :href="route('authors')">
                    {{ __('Edit Author') }}
                </flux:button>
            @endcan
            <flux:button variant="outline" color="zinc" icon="arrow-left" href="{{ route('music-plans') }}">
                {{ __('Back to Music Plans') }}
            </flux:button>
        </div>
        @endauth
    </div>
</div>