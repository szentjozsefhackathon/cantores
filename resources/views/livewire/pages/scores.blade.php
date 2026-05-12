<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ __('My Scores') }}</flux:heading>
                    <flux:subheading>{{ __('Private ABC and Gregorio GABC scores created by you.') }}</flux:subheading>
                </div>

                <flux:button variant="primary" icon="plus" :href="route('scores.create')" wire:navigate>
                    {{ __('Create Score') }}
                </flux:button>
            </div>

            <div class="mb-4 flex justify-end">
                <x-action-message on="score-deleted">
                    {{ __('Score deleted.') }}
                </x-action-message>
            </div>

            <div class="mb-6">
                <flux:field>
                    <flux:label>{{ __('Search') }}</flux:label>
                    <flux:input type="search" wire:model.live.debounce.500ms="search" icon="magnifying-glass" :placeholder="__('Search by score or music title')" />
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
                        <flux:table.column>{{ __('Format') }}</flux:table.column>
                        <flux:table.column>{{ __('Attached Music') }}</flux:table.column>
                        <flux:table.column>{{ __('Updated') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($scores as $score)
                            <flux:table.row wire:key="score-row-{{ $score->id }}">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $score->title }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="zinc" size="sm">{{ $score->format->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($score->music)
                                        <a href="{{ route('music-view', $score->music) }}" wire:navigate class="text-blue-600 hover:underline dark:text-blue-400">
                                            {{ $score->music->title }}
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Not attached') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $score->updated_at->translatedFormat('Y-m-d H:i') }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:button variant="ghost" size="sm" icon="pencil" :href="route('scores.edit', ['score' => $score->id])" wire:navigate :title="__('Edit')" />
                                        <flux:button variant="ghost" size="sm" icon="trash" wire:click="delete({{ $score->id }})" wire:confirm="{{ __('Are you sure you want to delete this score?') }}" :title="__('Delete')" />
                                    </div>
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
