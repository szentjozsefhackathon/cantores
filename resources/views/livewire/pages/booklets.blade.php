<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ __('My Booklets') }}</flux:heading>
                    <flux:subheading>{{ __('Printable booklets built from your music plans.') }}</flux:subheading>
                </div>

                <form method="POST" action="{{ route('booklets.store') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" icon="plus">
                        {{ __('New Booklet') }}
                    </flux:button>
                </form>
            </div>

            <div class="mb-6">
                <flux:field>
                    <flux:label>{{ __('Search') }}</flux:label>
                    <flux:input type="search" wire:model.live.debounce.500ms="search" icon="magnifying-glass" :placeholder="__('Search')" />
                </flux:field>
            </div>

            @if($booklets->isEmpty())
                <flux:callout variant="secondary" icon="book-open" class="border-dashed">
                    <flux:callout.heading>{{ __('No booklets yet') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Open a music plan and press Booklet to build one from its scores.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Title') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Page size') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Scores') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Updated') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($booklets as $booklet)
                            <flux:table.row wire:key="booklet-row-{{ $booklet->id }}">
                                <flux:table.cell>
                                    <a href="{{ route('booklets.edit', ['booklet' => $booklet->id]) }}" wire:navigate class="font-medium hover:underline">
                                        {{ $booklet->title }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <flux:badge color="zinc" size="sm">{{ $booklet->page_size->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <flux:badge color="zinc" size="sm">{{ $booklet->entries_count }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $booklet->updated_at->translatedFormat('Y-m-d H:i') }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="delete({{ $booklet->id }})"
                                        wire:confirm="{{ __('Delete this booklet?') }}"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($booklets->hasPages())
                    <div class="mt-4">
                        {{ $booklets->links() }}
                    </div>
                @endif
            @endif
        </flux:card>
    </div>
</div>
