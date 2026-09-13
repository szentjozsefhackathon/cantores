<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ __('My Projections') }}</flux:heading>
                    <flux:subheading>{{ __('Slides for the screen, built from your music plans.') }}</flux:subheading>
                </div>

                <flux:modal.trigger name="new-projection">
                    <flux:button variant="primary" icon="plus">
                        {{ __('New Projection') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <div class="mb-6">
                <flux:field>
                    <flux:label>{{ __('Search') }}</flux:label>
                    <flux:input type="search" wire:model.live.debounce.500ms="search" icon="magnifying-glass" :placeholder="__('Search')" />
                </flux:field>
            </div>

            @if($projections->isEmpty())
                <flux:callout variant="secondary" icon="presentation" class="border-dashed">
                    <flux:callout.heading>{{ __('No projections yet') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Open a music plan and press Projection to build one from its scores.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Title') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Screen shape') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Scores') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Updated') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($projections as $projection)
                            <flux:table.row wire:key="projection-row-{{ $projection->id }}">
                                <flux:table.cell>
                                    <a href="{{ route('projections.edit', ['projection' => $projection->id]) }}" wire:navigate class="font-medium hover:underline">
                                        {{ $projection->title }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <flux:badge color="zinc" size="sm">{{ $projection->ratio->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <flux:badge color="zinc" size="sm">{{ $projection->entries_count }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $projection->updated_at->translatedFormat('Y-m-d H:i') }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center justify-end gap-0.5">
                                        {{-- Straight to the projector, without
                                             opening the editor first: on a Sunday
                                             morning the deck is already finished. --}}
                                        <flux:tooltip :content="__('Present')">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="presentation"
                                                :aria-label="__('Present')"
                                                href="{{ route('projections.present', ['projection' => $projection->id]) }}"
                                            />
                                        </flux:tooltip>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="delete({{ $projection->id }})"
                                            wire:confirm="{{ __('Delete this projection?') }}"
                                        />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($projections->hasPages())
                    <div class="mt-4">
                        {{ $projections->links() }}
                    </div>
                @endif
            @endif
        </flux:card>
    </div>

    {{-- A projection is the scores of one service, so it starts from that
         service's plan. --}}
    <flux:modal name="new-projection" class="w-full max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('New Projection') }}</flux:heading>
                <flux:subheading>{{ __('Choose the music plan the projection is for.') }}</flux:subheading>
            </div>

            <flux:input
                type="search"
                wire:model.live.debounce.400ms="planSearch"
                icon="magnifying-glass"
                :placeholder="__('Search celebrations')"
            />

            <div class="max-h-96 space-y-1 overflow-y-auto">
                @forelse($this->selectablePlans as $plan)
                    <button
                        type="button"
                        wire:key="plan-{{ $plan->id }}"
                        wire:click="createFromPlan({{ $plan->id }})"
                        class="flex w-full items-center justify-between gap-3 rounded-md border border-zinc-200 px-3 py-2 text-left hover:border-blue-500 dark:border-zinc-700"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">
                                {{ $plan->celebration_name ?: __('Untitled plan') }}
                            </span>
                            @if($plan->actual_date)
                                <span class="block text-xs text-zinc-500">
                                    {{ $plan->actual_date->translatedFormat('Y. F j.') }}
                                </span>
                            @endif
                        </span>
                        <flux:icon name="chevron-right" variant="micro" class="shrink-0 text-zinc-400" />
                    </button>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('No music plans found.') }}</flux:text>
                @endforelse
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <flux:button size="sm" variant="ghost" wire:click="createBlank">
                    {{ __('Start without a plan') }}
                </flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
