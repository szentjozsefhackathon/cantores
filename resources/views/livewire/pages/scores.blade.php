<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ __('My Scores') }}</flux:heading>
                    <flux:subheading>{{ __('Private scores created by you.') }}</flux:subheading>
                </div>

                <flux:button variant="primary" icon="plus" :href="route('scores.create')" wire:navigate>
                    {{ __('Create Score') }}
                </flux:button>
            </div>


            <div class="mb-6">
                <flux:field>
                    <flux:label>{{ __('Search') }}</flux:label>
                    <flux:input type="search" wire:model.live.debounce.500ms="search" icon="magnifying-glass" :placeholder="__('Search')" />
                </flux:field>
            </div>

            @if($scores->isEmpty())
                <flux:callout variant="secondary" icon="book-open-text" class="border-dashed">
                    <flux:callout.heading>{{ __('No scores found') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Create your first private textual score.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Title') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Updated') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($scores as $score)
                            <flux:table.row wire:key="score-row-{{ $score->id }}">
                                <flux:table.cell>
                                    <div class="flex flex-wrap items-center gap-1.5 font-medium">
                                        <a href="{{ route('scores.edit', ['score' => $score->id]) }}" wire:navigate class="hover:underline">
                                            {{ $score->title }}
                                        </a>
                                        <x-score-format-badge :format="$score->format" />
                                        @if($score->share_token)
                                            <flux:icon name="link" size="sm" class="text-blue-500 dark:text-blue-400" :title="__('Secret link active')" />
                                        @endif
                                        @foreach($score->folders as $folder)
                                            <flux:badge color="violet" size="sm" :title="$folder->name">
                                                <flux:icon name="folder" variant="micro" class="mr-0.5" />
                                                {{ \Illuminate\Support\Str::limit($folder->name, 20) }}
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                    @if($score->hasIncipit())
                                        <x-incipit-image :src="$score->incipitUrl()" :alt="__('Incipit')"
                                            class="mt-1 max-w-[400px]"
                                            img-class="block h-auto max-h-20 w-auto max-w-full" />
                                    @endif
                                    @if($score->music)
                                        <div class="mt-1">
                                            <a href="{{ route('music-view', $score->music) }}" wire:navigate class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                                                {{ $score->music->title }}
                                            </a>
                                        </div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $score->updated_at->translatedFormat('Y-m-d H:i') }}</span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($scores->hasPages())
                    <div class="mt-4">
                        {{ $scores->links() }}
                    </div>
                @endif
            @endif
        </flux:card>
    </div>
</div>
